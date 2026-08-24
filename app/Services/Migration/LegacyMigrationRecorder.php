<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyMigrationRecorder
{
    /**
     * @return array{source: int, mapping: int, missing: list<string>, stale: list<string>}
     */
    public function validateContratsPaeSchema(): array
    {
        $sourceColumns = collect(Schema::connection('legacy')->getColumnListing('contrats_pae'));

        $mappedColumns = DB::table('correspondances_colonnes_contrats_pae')
            ->orderBy('nom_colonne_source')
            ->pluck('nom_colonne_source');

        $result = [
            'source' => $sourceColumns->count(),
            'mapping' => $mappedColumns->count(),
            'missing' => $sourceColumns->diff($mappedColumns)->values()->all(),
            'stale' => $mappedColumns->diff($sourceColumns)->values()->all(),
        ];

        if ($result['missing'] !== [] || $result['stale'] !== []) {
            throw new RuntimeException(sprintf(
                'Couverture contrats_pae invalide : %d colonnes source, %d mappings, absentes=[%s], obsoletes=[%s].',
                $result['source'],
                $result['mapping'],
                implode(', ', $result['missing']),
                implode(', ', $result['stale']),
            ));
        }

        return $result;
    }

    public function start(string $sourceVersion): int
    {
        return DB::table('executions_migration')->insertGetId([
            'uuid_public' => (string) Str::uuid(),
            'version_source' => $sourceVersion,
            'statut' => 'EN_COURS',
            'demarree_le' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function failStaleExecutions(string $sourceVersionPrefix): int
    {
        $now = now();

        return DB::table('executions_migration')
            ->where('statut', 'EN_COURS')
            ->where('version_source', 'like', $sourceVersionPrefix.'%')
            ->update([
                'statut' => 'ECHEC',
                'compteurs' => json_encode([
                    'erreur' => 'Exécution précédente interrompue sans finalisation ; reprise par une nouvelle commande.',
                ], JSON_THROW_ON_ERROR),
                'terminee_le' => $now,
                'updated_at' => $now,
            ]);
    }

    /** @param array<string, mixed> $counters */
    public function complete(int $executionId, array $counters): void
    {
        DB::table('executions_migration')->where('id', $executionId)->update([
            'statut' => 'TERMINEE',
            'compteurs' => json_encode($counters, JSON_THROW_ON_ERROR),
            'terminee_le' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $counters */
    public function fail(int $executionId, array $counters, string $message): void
    {
        $counters['erreur'] = $message;

        DB::table('executions_migration')->where('id', $executionId)->update([
            'statut' => 'ECHEC',
            'compteurs' => json_encode($counters, JSON_THROW_ON_ERROR),
            'terminee_le' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @var list<array<string, mixed>> */
    private array $conservationsBuffer = [];

    /** @var list<array<string, mixed>> */
    private array $correspondencesBuffer = [];

    /** @var list<array<string, mixed>> */
    private array $anomaliesBuffer = [];

    /** Nombre d'entrées en attente de flush dans le buffer conservations. */
    public function conservationsBufferCount(): int
    {
        return count($this->conservationsBuffer);
    }

    /**
     * Oublie les écritures de traçabilité du chunk courant après son rollback.
     * Les chunks précédents ont déjà été flushés atomiquement avec leurs données métier.
     */
    public function discardPending(): void
    {
        $this->conservationsBuffer = [];
        $this->correspondencesBuffer = [];
        $this->anomaliesBuffer = [];
    }

    public function flush(): void
    {
        if (! empty($this->conservationsBuffer)) {
            // Dédupliquer par (contrat_pae_ancien_id, empreinte_originale) en ne gardant
            // que la dernière occurrence (celle avec les FK résolues).
            $deduplicated = [];
            foreach ($this->conservationsBuffer as $row) {
                $key = $row['contrat_pae_ancien_id'].'|'.$row['empreinte_originale'];
                $deduplicated[$key] = $row;
            }
            $uniqueRows = array_values($deduplicated);

            DB::table('conservations_contrats_pae')->upsert(
                $uniqueRows,
                ['contrat_pae_ancien_id', 'empreinte_originale'],
                ['execution_migration_id', 'beneficiaire_id', 'stage_id', 'contrat_id', 'nombre_colonnes_source', 'donnees_originales', 'version_schema_source', 'importe_le', 'updated_at']
            );
            $this->conservationsBuffer = [];
        }

        if (! empty($this->correspondencesBuffer)) {
            $deduplicated = [];
            foreach ($this->correspondencesBuffer as $row) {
                $key = $row['table_source'].'|'.$row['id_source'].'|'.$row['table_cible'];
                $deduplicated[$key] = $row;
            }

            DB::table('correspondances_ancien_systeme')->upsert(
                array_values($deduplicated),
                ['table_source', 'id_source', 'table_cible'],
                ['execution_migration_id', 'id_cible', 'empreinte_source', 'updated_at']
            );
            $this->correspondencesBuffer = [];
        }

        if (! empty($this->anomaliesBuffer)) {
            $deduplicated = [];
            foreach ($this->anomaliesBuffer as $row) {
                $deduplicated[$row['cle_idempotence']] = $row;
            }

            DB::table('anomalies_migration')->upsert(
                array_values($deduplicated),
                ['cle_idempotence'],
                ['execution_migration_id', 'gravite', 'description', 'donnees', 'updated_at']
            );
            $this->anomaliesBuffer = [];
        }
    }

    public function preserveContrat(
        int $executionId,
        object $legacyContrat,
        ?int $beneficiaireId,
        ?int $stageId,
        ?int $contratId,
        int $sourceColumnCount,
    ): void {
        $data = (array) $legacyContrat;
        $sourceId = $data['id'] ?? null;
        if (! is_numeric($sourceId)) {
            throw new RuntimeException('La ligne contrats_pae à conserver ne possède pas un identifiant numérique.');
        }
        ksort($data);
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $fingerprint = hash('sha256', $json);

        $this->conservationsBuffer[] = [
            'contrat_pae_ancien_id' => (int) $sourceId,
            'empreinte_originale' => $fingerprint,
            'execution_migration_id' => $executionId,
            'beneficiaire_id' => $beneficiaireId,
            'stage_id' => $stageId,
            'contrat_id' => $contratId,
            'nombre_colonnes_source' => $sourceColumnCount,
            'donnees_originales' => $json,
            'version_schema_source' => "mysql-contrats_pae-{$sourceColumnCount}",
            'importe_le' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        if (count($this->conservationsBuffer) >= 1000) {
            $this->flush();
        }
    }

    /** @param array<string, mixed> $sourceData */
    public function correspondence(
        int $executionId,
        string $sourceTable,
        string|int $sourceId,
        string $targetTable,
        int $targetId,
        array $sourceData = [],
    ): void {
        $fingerprint = $sourceData === [] ? null : $this->fingerprint($sourceData);

        $this->correspondencesBuffer[] = [
            'table_source' => $sourceTable,
            'id_source' => (string) $sourceId,
            'table_cible' => $targetTable,
            'execution_migration_id' => $executionId,
            'id_cible' => $targetId,
            'empreinte_source' => $fingerprint,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        if (count($this->correspondencesBuffer) >= 1000) {
            $this->flush();
        }
    }

    /** @param array<string, mixed> $data */
    public function anomaly(
        int $executionId,
        string $code,
        string $sourceTable,
        string|int|null $sourceId,
        string $description,
        array $data = [],
        string $severity = 'BLOQUANTE',
    ): void {
        $status = 'A_RECONCILIER';
        $sourceId = $sourceId === null ? null : (string) $sourceId;

        $this->anomaliesBuffer[] = [
            'cle_idempotence' => hash('sha256', implode('|', [$code, $sourceTable, $sourceId ?? '<null>', $status])),
            'code' => $code,
            'table_source' => $sourceTable,
            'id_source' => $sourceId,
            'statut' => $status,
            'execution_migration_id' => $executionId,
            'gravite' => $severity,
            'description' => $description,
            'donnees' => $data === [] ? null : json_encode($data, JSON_THROW_ON_ERROR),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        if (count($this->anomaliesBuffer) >= 1000) {
            $this->flush();
        }
    }

    /** @param array<string, mixed> $data */
    public function fingerprint(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
