<?php

namespace Tests\Feature\Console;

use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Internship\Stage;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use App\Services\Migration\LegacyMigrationRecorder;
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

        Schema::connection('legacy')->create('pointage_models', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('stagiaire_id')->nullable();
            $table->date('date_pointage')->nullable();
            $table->string('mois')->nullable();
            $table->text('commentaire')->nullable();
            $table->unsignedTinyInteger('status_dmg')->default(0);
            $table->unsignedTinyInteger('status_ca')->default(0);
            $table->unsignedBigInteger('etape_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('legacy')->create('paiement_models', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('dossier_id')->nullable();
            $table->unsignedBigInteger('stagiaire_id')->nullable();
            $table->string('mois')->nullable();
            $table->decimal('montant', 15, 2)->default(0);
            $table->unsignedTinyInteger('status_dmg')->default(0);
            $table->unsignedTinyInteger('status_cb')->default(0);
            $table->string('status_ac')->nullable();
            $table->unsignedBigInteger('created_by_cb')->nullable();
            $table->timestamp('date_vise_cb')->nullable();
            $table->timestamp('date_vise_ac')->nullable();
            $table->timestamp('date_confirm_pay')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('legacy')->create('multi_dossiers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('mois')->nullable();
            $table->string('attestation_path')->nullable();
            $table->string('etat_financier_path')->nullable();
            $table->string('observation')->nullable();
            $table->boolean('group_by_dmg')->default(false);
            $table->string('status_cb')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('legacy')->create('operations', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_operation')->nullable();
            $table->string('mois')->nullable();
            $table->unsignedBigInteger('type_financement_id')->nullable();
            $table->decimal('montant_op', 15, 2)->nullable();
            $table->decimal('montant', 15, 2)->nullable();
            $table->string('status_operation')->nullable();
            $table->unsignedBigInteger('borderau_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('legacy')->create('borderaus', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_bordereau')->nullable();
            $table->string('numero_borderau')->nullable();
            $table->string('mois')->nullable();
            $table->unsignedBigInteger('type_financement_id')->nullable();
            $table->decimal('montant_total', 15, 2)->nullable();
            $table->string('status_borderau')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('legacy')->create('contrat_etape', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('etape_id')->nullable();
            $table->unsignedBigInteger('contrat_id')->nullable();
            $table->text('commentaire')->nullable();
            $table->unsignedBigInteger('pointage_id')->nullable();
            $table->unsignedBigInteger('paiement_id')->nullable();
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
            'mois' => '2026-08',
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

    public function test_duplicate_legacy_dossier_numbers_are_preserved_with_deterministic_target_numbers(): void
    {
        Agence::factory()->create(['ancien_id' => 12]);
        Agence::factory()->create(['ancien_id' => 13]);
        SourceFinancement::factory()->create(['ancien_id' => 3]);

        DB::connection('legacy')->table('dossiers')->insert([
            [
                'id' => 3759,
                'identifiant' => 'DM122025-3759',
                'mois' => '2025-12',
                'type_financement_id' => 3,
                'agence_id' => 12,
                'group_by_dmg' => 1,
                'created_at' => '2025-12-11 15:40:24',
                'updated_at' => '2025-12-31 11:09:02',
            ],
            [
                'id' => 3760,
                'identifiant' => 'DM122025-3759',
                'mois' => '2025-12',
                'type_financement_id' => 3,
                'agence_id' => 13,
                'group_by_dmg' => 1,
                'created_at' => '2025-12-11 15:57:57',
                'updated_at' => '2025-12-31 11:09:02',
            ],
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'dossiers_paiement'])->assertExitCode(0);
        $this->artisan('migrate:legacy-data', ['--step' => 'dossiers_paiement'])->assertExitCode(0);

        $this->assertDatabaseHas('dossiers_paiement', [
            'ancien_id' => 3759,
            'numero' => 'DM122025-3759',
        ]);
        $this->assertDatabaseHas('dossiers_paiement', [
            'ancien_id' => 3760,
            'numero' => 'DM122025-3759-LEGACY-3760',
        ]);
        $this->assertDatabaseCount('dossiers_paiement', 2);
        $this->assertDatabaseCount('anomalies_migration', 2);
    }

    public function test_multi_agency_legacy_dossier_is_preserved_without_inventing_an_agency(): void
    {
        SourceFinancement::factory()->create(['ancien_id' => 3]);
        DB::connection('legacy')->table('dossiers')->insert([
            'id' => 3900,
            'identifiant' => 'PS122025-3900',
            'mois' => '2025-12',
            'type_financement_id' => 3,
            'agence_id' => null,
            'group_by_dmg' => 1,
            'created_at' => '2025-12-15 10:00:00',
            'updated_at' => '2025-12-15 10:00:00',
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'dossiers_paiement'])->assertExitCode(0);

        $this->assertDatabaseHas('dossiers_paiement', [
            'ancien_id' => 3900,
            'numero' => 'PS122025-3900',
            'agence_id' => null,
        ]);
        $this->assertDatabaseHas('anomalies_migration', [
            'code' => 'DOSSIER_AGENCE_A_RECONCILIER',
            'table_source' => 'dossiers',
            'id_source' => '3900',
        ]);
    }

    public function test_payment_migration_uses_business_month_and_repairs_existing_nature_idempotently(): void
    {
        $agence = Agence::factory()->create(['ancien_id' => 12]);
        $source = SourceFinancement::factory()->create(['ancien_id' => 3]);
        $stage = Stage::factory()->create([
            'ancien_id' => 502,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'date_debut' => '2026-08-14',
        ]);

        DB::connection('legacy')->table('paiement_models')->insert([
            'id' => 9002,
            'stagiaire_id' => 502,
            'mois' => '2026-08',
            'montant' => 45000,
            'created_at' => '2026-09-04 08:00:00',
            'updated_at' => '2026-09-04 08:00:00',
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'paiements'])->assertExitCode(0);

        $droit = DroitPaiement::where('ancien_id', 9002)->firstOrFail();
        $this->assertSame('DEMARRAGE', $droit->nature);
        $this->assertSame('2026-08', $droit->periode->code);

        $droit->update(['nature' => 'PRESENCE']);
        Paiement::where('ancien_id', 9002)->update(['statut' => 'EN_DOSSIER']);

        $this->artisan('migrate:legacy-data', ['--step' => 'paiements'])->assertExitCode(0);

        $this->assertSame(1, DroitPaiement::where('ancien_id', 9002)->count());
        $this->assertDatabaseHas('droits_paiement', [
            'id' => $droit->id,
            'ancien_id' => 9002,
            'nature' => 'DEMARRAGE',
            'annule_le' => null,
        ]);
        $this->assertDatabaseHas('paiements', [
            'ancien_id' => 9002,
            'statut' => 'EN_DOSSIER',
        ]);
    }

    public function test_pointages_from_the_same_business_month_become_versions_of_one_demarrage(): void
    {
        $agence = Agence::factory()->create(['ancien_id' => 13]);
        $source = SourceFinancement::factory()->create(['ancien_id' => 4]);
        $stage = Stage::factory()->create([
            'ancien_id' => 503,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'date_debut' => '2026-08-10',
        ]);

        DB::connection('legacy')->table('pointage_models')->insert([
            [
                'id' => 7001,
                'stagiaire_id' => 503,
                'mois' => '2026-08',
                'commentaire' => 'Première saisie',
                'status_dmg' => 0,
                'status_ca' => 1,
                'etape_id' => 13,
                'created_at' => '2026-09-01 08:00:00',
                'updated_at' => '2026-09-01 08:00:00',
            ],
            [
                'id' => 7002,
                'stagiaire_id' => 503,
                'mois' => '2026-08',
                'commentaire' => 'Resoumission',
                'status_dmg' => 1,
                'status_ca' => 1,
                'etape_id' => 13,
                'created_at' => '2026-09-02 08:00:00',
                'updated_at' => '2026-09-02 08:00:00',
            ],
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'pointages'])->assertExitCode(0);
        $this->artisan('migrate:legacy-data', ['--step' => 'pointages'])->assertExitCode(0);

        $pointage = Pointage::where('stage_id', $stage->id)->firstOrFail();
        $this->assertSame('DEMARRAGE', $pointage->nature);
        $this->assertSame('2026-08', $pointage->periode->code);
        $this->assertSame(1, Pointage::where('stage_id', $stage->id)->count());
        $this->assertSame(2, VersionPointage::where('pointage_id', $pointage->id)->count());
        $this->assertSame(2, $pointage->fresh()->version_courante);
    }

    public function test_validated_legacy_payment_keeps_its_accounting_status_and_date(): void
    {
        $agence = Agence::factory()->create(['ancien_id' => 14]);
        $source = SourceFinancement::factory()->create(['ancien_id' => 8]);
        Stage::factory()->create([
            'ancien_id' => 504,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'date_debut' => '2026-08-10',
        ]);
        DB::connection('legacy')->table('paiement_models')->insert([
            'id' => 9003,
            'stagiaire_id' => 504,
            'mois' => '2026-08',
            'montant' => 45000,
            'status_dmg' => 1,
            'status_cb' => 1,
            'status_ac' => 'validated',
            'date_confirm_pay' => '2026-09-05 09:30:00',
            'created_at' => '2026-08-20 08:00:00',
            'updated_at' => '2026-09-05 09:30:00',
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'paiements'])->assertExitCode(0);

        $paiement = Paiement::where('ancien_id', 9003)->firstOrFail();
        $this->assertSame('VALIDE_AC', $paiement->statut);
        $this->assertSame('2026-09-05 09:30:00', $paiement->paye_le?->format('Y-m-d H:i:s'));
    }

    public function test_it_reconstructs_group_operation_and_bordereau_links_idempotently(): void
    {
        $agence = Agence::factory()->create(['ancien_id' => 20]);
        $source = SourceFinancement::factory()->create(['ancien_id' => 7]);
        $periode = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
        ]);
        $dossier = DossierPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'ancien_id' => 501,
            'periode_id' => $periode->id,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'numero' => 'DM082026-501',
            'nature' => 'DM',
            'statut' => 'TRANSMIS_CB',
            'montant_total' => 45000,
        ]);

        DB::connection('legacy')->table('multi_dossiers')->insert([
            'id' => 31,
            'name' => 'DM2026-08-31-G',
            'mois' => '2026-08',
            'group_by_dmg' => 1,
            'created_at' => '2026-08-10 08:00:00',
            'updated_at' => '2026-08-10 08:00:00',
        ]);
        DB::connection('legacy')->table('operations')->insert([
            'id' => 41,
            'numero_operation' => 'OP-41',
            'mois' => '2026-08',
            'type_financement_id' => 7,
            'montant_op' => 45000,
            'status_operation' => 'processed',
            'borderau_id' => 51,
            'created_at' => '2026-08-11 08:00:00',
            'updated_at' => '2026-08-11 08:00:00',
        ]);
        DB::connection('legacy')->table('borderaus')->insert([
            'id' => 51,
            'numero_bordereau' => 'BORD-51',
            'mois' => '2026-08',
            'type_financement_id' => 7,
            'montant_total' => 45000,
            'status_borderau' => 'pending',
            'created_at' => '2026-08-12 08:00:00',
            'updated_at' => '2026-08-12 08:00:00',
        ]);
        DB::connection('legacy')->table('dossiers')->insert([
            'id' => 501,
            'identifiant' => 'DM082026-501',
            'mois' => '2026-08',
            'type_financement_id' => 7,
            'agence_id' => 20,
            'operation_id' => 41,
            'multi_dossier_id' => 31,
            'group_by_dmg' => 1,
            'created_at' => '2026-08-10 08:00:00',
            'updated_at' => '2026-08-10 08:00:00',
        ]);

        foreach (['dossiers_groupes', 'operations', 'bordereaux', 'dossiers_groupes', 'operations', 'bordereaux'] as $step) {
            $this->artisan('migrate:legacy-data', ['--step' => $step])->assertExitCode(0);
        }

        $groupe = DossierGroupe::where('ancien_id', 31)->firstOrFail();
        $ordre = OrdrePaiement::where('ancien_id', 41)->firstOrFail();
        $bordereau = BordereauPaiement::where('ancien_id', 51)->firstOrFail();

        $this->assertSame('TRANSMIS_CB', $groupe->statut);
        $this->assertSame('EN_BORDEREAU', $ordre->statut);
        $this->assertSame('TRANSMIS_AC', $bordereau->statut);
        $this->assertSame($ordre->id, $dossier->fresh()->ordre_paiement_id);
        $this->assertSame($bordereau->id, $ordre->fresh()->bordereau_paiement_id);
        $this->assertDatabaseCount('lignes_dossiers_groupes', 1);
        $this->assertDatabaseHas('lignes_dossiers_groupes', [
            'dossier_groupe_id' => $groupe->id,
            'dossier_paiement_id' => $dossier->id,
            'retire_le' => null,
        ]);
    }

    public function test_event_history_is_idempotent_and_uses_the_mapped_legacy_author(): void
    {
        $stage = Stage::factory()->create(['ancien_id' => 801]);
        $definition = DefinitionParcours::create([
            'code' => 'STAGE_EVENT_TEST',
            'nom' => 'Stage event test',
            'version' => 1,
            'active' => true,
        ]);
        $etape = EtapeParcours::create([
            'definition_parcours_id' => $definition->id,
            'code' => 'CIP_MES_STAGIAIRES',
            'nom' => 'CIP',
        ]);
        $instance = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => $stage->id,
            'corbeille_actuelle' => 'cip_mes_stagiaires',
        ]);
        $author = User::factory()->create();
        $executionId = app(LegacyMigrationRecorder::class)->start('author-map-test');
        DB::table('correspondances_ancien_systeme')->insert([
            'execution_migration_id' => $executionId,
            'table_source' => 'users',
            'id_source' => '88',
            'table_cible' => 'users',
            'id_cible' => $author->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('legacy')->table('contrat_etape')->insert([
            'id' => 991,
            'user_id' => 88,
            'etape_id' => 2,
            'contrat_id' => 801,
            'commentaire' => 'Transmission legacy',
            'created_at' => '2026-08-10 10:00:00',
            'updated_at' => '2026-08-10 10:00:00',
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'evenements'])->assertExitCode(0);
        $this->artisan('migrate:legacy-data', ['--step' => 'evenements'])->assertExitCode(0);

        $this->assertDatabaseCount('evenements_parcours', 1);
        $this->assertDatabaseHas('evenements_parcours', [
            'instance_parcours_id' => $instance->id,
            'auteur_id' => $author->id,
            'cle_idempotence' => 'mig_991_'.$instance->id,
        ]);
        $this->assertDatabaseHas('correspondances_ancien_systeme', [
            'table_source' => 'contrat_etape',
            'id_source' => '991',
            'table_cible' => 'evenements_parcours',
        ]);
    }
}
