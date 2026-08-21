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

    public function test_it_validates_columns_and_preserves_each_source_fingerprint_idempotently(): void
    {
        foreach (['id', 'numero_aej', 'file_cmu'] as $column) {
            DB::table('correspondances_colonnes_contrats_pae')->insert([
                'nom_colonne_source' => $column,
                'strategie_conservation' => 'NORMALISEE_ET_ARCHIVEE',
                'obligatoire' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('legacy')->table('contrats_pae')->insert([
            'id' => 91,
            'numero_aej' => 'AEJ-91',
            'file_cmu' => 'cmu/91.pdf',
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
        $recorder->preserveContrat($executionId, $row, null, null, null, 3);
        $recorder->preserveContrat($executionId, $row, null, null, null, 3);
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

        $this->assertDatabaseCount('conservations_contrats_pae', 2);
        $this->assertSame(
            2,
            DB::table('conservations_contrats_pae')->where('contrat_pae_ancien_id', 91)->distinct()->count('empreinte_originale'),
        );
    }

    public function test_it_refuses_an_unmapped_source_column(): void
    {
        DB::table('correspondances_colonnes_contrats_pae')->insert([
            'nom_colonne_source' => 'id',
            'strategie_conservation' => 'NORMALISEE_ET_ARCHIVEE',
            'obligatoire' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('absentes=[numero_aej, file_cmu]');

        app(LegacyMigrationRecorder::class)->validateContratsPaeSchema();
    }
}
