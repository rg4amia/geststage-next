<?php

namespace Tests\Feature\Services\Migration;

use App\Services\Migration\LegacyMigrationRecorder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LegacyMigrationRecorderTest extends TestCase
{
    use RefreshDatabase;

    private string $legacyDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyDatabasePath = sys_get_temp_dir().'/geststage-recorder-'.Str::uuid().'.sqlite';
        touch($this->legacyDatabasePath);
        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => $this->legacyDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('legacy');

        Schema::connection('legacy')->create('contrats_pae', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_aej');
            $table->string('file_cmu')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('legacy');
        DB::purge('legacy');

        if (is_file($this->legacyDatabasePath)) {
            unlink($this->legacyDatabasePath);
        }

        parent::tearDown();
    }

    private function seedColumnMappings(array $columns): void
    {
        $now = now()->toDateTimeString();
        $normalisations = [
            'id' => ['conservations_contrats_pae', 'contrat_pae_ancien_id'],
            'numero_aej' => ['beneficiaires', 'numero_aej'],
            'file_cmu' => ['versions_documents', 'chemin'],
        ];

        foreach ($columns as $colonne) {
            [$tableCible, $colonneCible] = $normalisations[$colonne] ?? [null, null];
            DB::table('correspondances_colonnes_contrats_pae')->insert([
                'nom_colonne_source' => $colonne,
                'table_cible' => $tableCible,
                'colonne_cible' => $colonneCible,
                'strategie_conservation' => $tableCible === null ? 'A_RECONCILIER' : 'NORMALISEE_ET_ARCHIVEE',
                'obligatoire' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function test_it_validates_columns_and_preserves_each_source_fingerprint_idempotently(): void
    {
        $this->seedColumnMappings(['id', 'numero_aej', 'file_cmu']);

        DB::connection('legacy')->table('contrats_pae')->insert([
            'id' => 91, 'numero_aej' => 'AEJ-001', 'file_cmu' => null,
        ]);
        DB::connection('legacy')->table('contrats_pae')->insert([
            'numero_aej' => 'AEJ-002', 'file_cmu' => null,
        ]);
        DB::connection('legacy')->table('contrats_pae')->insert([
            'numero_aej' => 'AEJ-003', 'file_cmu' => null,
        ]);

        $recorder = app(LegacyMigrationRecorder::class);
        $this->assertSame([
            'source' => 3,
            'mapping' => 3,
            'missing' => [],
            'stale' => [],
        ], $recorder->validateContratsPaeSchema());

        $executionId = $recorder->start('test-v1');
        $row = DB::connection('legacy')->table('contrats_pae')->find(91);
        // Two calls with the same source data: the dedup guard in flush() keeps only one.
        $recorder->preserveContrat($executionId, $row, null, null, null, 3);
        $recorder->preserveContrat($executionId, $row, null, null, null, 3);
        $recorder->flush();
        $this->assertDatabaseCount('conservations_contrats_pae', 1);

        DB::connection('legacy')->table('contrats_pae')->where('id', 91)->update(['file_cmu' => 'cmu/91-v2.pdf']);
        $secondExecutionId = $recorder->start('test-v2');
        $recorder->preserveContrat(
            $secondExecutionId,
            DB::connection('legacy')->table('contrats_pae')->find(91),
            null,
            null,
            null,
            3,
        );
        $recorder->flush();

        $this->assertDatabaseCount('conservations_contrats_pae', 2);
        $this->assertSame(
            2,
            DB::table('conservations_contrats_pae')->where('contrat_pae_ancien_id', 91)->distinct()->count('empreinte_originale'),
        );
    }

    public function test_it_refuses_an_unmapped_source_column(): void
    {
        $this->seedColumnMappings(['id']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('absentes=[numero_aej, file_cmu]');

        app(LegacyMigrationRecorder::class)->validateContratsPaeSchema();
    }

    public function test_it_batches_duplicate_correspondences_and_anomalies_idempotently(): void
    {
        $recorder = app(LegacyMigrationRecorder::class);
        $executionId = $recorder->start('test-batches');

        $recorder->correspondence($executionId, 'legacy_rows', 10, 'targets', 100, ['id' => 10]);
        $recorder->correspondence($executionId, 'legacy_rows', 10, 'targets', 101, ['id' => 10]);
        $recorder->anomaly($executionId, 'ROW_INVALID', 'legacy_rows', 10, 'Première description');
        $recorder->anomaly($executionId, 'ROW_INVALID', 'legacy_rows', 10, 'Description finale');
        $recorder->flush();

        $this->assertDatabaseCount('correspondances_ancien_systeme', 1);
        $this->assertDatabaseHas('correspondances_ancien_systeme', [
            'table_source' => 'legacy_rows',
            'id_source' => '10',
            'table_cible' => 'targets',
            'id_cible' => 101,
        ]);
        $this->assertDatabaseCount('anomalies_migration', 1);
        $this->assertDatabaseHas('anomalies_migration', [
            'code' => 'ROW_INVALID',
            'table_source' => 'legacy_rows',
            'id_source' => '10',
            'description' => 'Description finale',
        ]);

        $recorder->anomaly($executionId, 'DISCARDED', 'legacy_rows', 11, 'À annuler');
        $recorder->discardPending();
        $recorder->flush();

        $this->assertDatabaseMissing('anomalies_migration', ['code' => 'DISCARDED']);
    }
}
