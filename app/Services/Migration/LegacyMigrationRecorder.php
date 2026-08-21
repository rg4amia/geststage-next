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

        DB::table('conservations_contrats_pae')->updateOrInsert(
            [
                'contrat_pae_ancien_id' => (int) $sourceId,
                'empreinte_originale' => $fingerprint,
            ],
            [
                'execution_migration_id' => $executionId,
                'beneficiaire_id' => $beneficiaireId,
                'stage_id' => $stageId,
                'contrat_id' => $contratId,
                'nombre_colonnes_source' => $sourceColumnCount,
                'donnees_originales' => $json,
                'version_schema_source' => "mysql-contrats_pae-{$sourceColumnCount}",
                'importe_le' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
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

        DB::table('correspondances_ancien_systeme')->updateOrInsert(
            [
                'table_source' => $sourceTable,
                'id_source' => (string) $sourceId,
                'table_cible' => $targetTable,
            ],
            [
                'execution_migration_id' => $executionId,
                'id_cible' => $targetId,
                'empreinte_source' => $fingerprint,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
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
        $keys = [
            'code' => $code,
            'table_source' => $sourceTable,
            'id_source' => $sourceId === null ? null : (string) $sourceId,
            'statut' => 'A_RECONCILIER',
        ];

        DB::table('anomalies_migration')->updateOrInsert($keys, [
            'execution_migration_id' => $executionId,
            'gravite' => $severity,
            'description' => $description,
            'donnees' => $data === [] ? null : json_encode($data, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
