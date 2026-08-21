<?php

namespace Tests\Feature\Console;

use App\Models\Internship\Stage;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrateLegacyDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $legacyDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyDatabasePath = sys_get_temp_dir().'/geststage-legacy-'.Str::uuid().'.sqlite';
        touch($this->legacyDatabasePath);

        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => $this->legacyDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('legacy');

        Schema::connection('legacy')->create('dossiers', function (Blueprint $table): void {
            $table->id();
            $table->string('identifiant');
            $table->string('mois');
            $table->unsignedBigInteger('type_financement_id')->nullable();
            $table->unsignedBigInteger('agence_id')->nullable();
            $table->unsignedBigInteger('operation_id')->nullable();
            $table->unsignedBigInteger('multi_dossier_id')->nullable();
            $table->unsignedBigInteger('created_by_cb')->nullable();
            $table->timestamp('date_cb')->nullable();
            $table->string('status_cb')->nullable();
            $table->boolean('group_by_dmg')->default(false);
            $table->timestamp('group_by_dmg_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('legacy')->create('paiement_models', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('dossier_id')->nullable();
            $table->unsignedBigInteger('stagiaire_id')->nullable();
            $table->decimal('montant', 15, 2)->default(0);
            $table->unsignedTinyInteger('status_dmg')->default(0);
            $table->unsignedTinyInteger('status_cb')->default(0);
            $table->unsignedBigInteger('created_by_cb')->nullable();
            $table->timestamp('date_vise_cb')->nullable();
            $table->timestamps();
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

    public function test_it_backfills_open_legacy_dossiers_into_dossiers_paiement(): void
    {
        $agence = Agence::factory()->create(['ancien_id' => 12]);
        $source = SourceFinancement::factory()->create(['ancien_id' => 3]);
        $periode = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
        ]);

        $stage = Stage::factory()->create([
            'ancien_id' => 501,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
        ]);

        $droit = DroitPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'ancien_id' => 9001,
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $source->id,
            'nature' => 'PRESENCE',
            'montant' => 45000,
            'statut' => 'OUVERT',
        ]);

        $paiement = Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'ancien_id' => 9001,
            'droit_paiement_id' => $droit->id,
            'montant' => 45000,
            'statut' => 'A_TRAITER',
        ]);

        DB::connection('legacy')->table('dossiers')->insert([
            'id' => 77,
            'identifiant' => 'DM082026-7902',
            'mois' => '2026-08',
            'type_financement_id' => 3,
            'agence_id' => 12,
            'group_by_dmg' => 0,
            'created_at' => '2026-08-12 08:00:00',
            'updated_at' => '2026-08-12 08:00:00',
        ]);

        DB::connection('legacy')->table('paiement_models')->insert([
            'id' => 9001,
            'dossier_id' => 77,
            'stagiaire_id' => 501,
            'montant' => 45000,
            'status_dmg' => 1,
            'status_cb' => 0,
            'created_at' => '2026-08-12 08:00:00',
            'updated_at' => '2026-08-12 08:00:00',
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'dossiers_paiement'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('dossiers_paiement', [
            'ancien_id' => 77,
            'periode_id' => $periode->id,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'numero' => 'DM082026-7902',
            'nature' => 'DM',
            'statut' => 'BROUILLON',
        ]);

        $dossier = DossierPaiement::query()->where('ancien_id', 77)->firstOrFail();
        $this->assertSame('45000.00', $dossier->montant_total);
        $this->assertDatabaseHas('lignes_dossiers_paiement', [
            'dossier_paiement_id' => $dossier->id,
            'paiement_id' => $paiement->id,
            'retire_le' => null,
        ]);
        $this->assertDatabaseHas('paiements', [
            'id' => $paiement->id,
            'statut' => 'EN_DOSSIER',
        ]);
    }
}
