<?php

namespace Tests\Feature\Console;

use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Enums\VisaDesseEnum;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Contract\AvenantContrat;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DecisionPaiement;
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
use App\Models\Workflow\TacheParcours;
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

        Schema::connection('legacy')->create('contrats_pae', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('etat_chef_agence')->nullable();
        });

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
            $table->unsignedTinyInteger('status_cip')->default(0);
            $table->unsignedBigInteger('situationstage_id')->nullable();
            $table->unsignedBigInteger('etape_id')->nullable();
            $table->timestamp('date_ca')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('legacy')->create('paiement_models', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('dossier_id')->nullable();
            $table->unsignedBigInteger('stagiaire_id')->nullable();
            $table->unsignedBigInteger('pointage_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('mois')->nullable();
            $table->decimal('montant', 15, 2)->default(0);
            $table->unsignedTinyInteger('status_dmg')->default(0);
            $table->unsignedTinyInteger('status_ar')->default(0);
            $table->unsignedTinyInteger('status_cb')->default(0);
            $table->string('observation')->nullable();
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

    public function test_duplicate_operation_and_bordereau_numbers_are_preserved_idempotently(): void
    {
        SourceFinancement::factory()->create(['ancien_id' => 7]);
        DB::connection('legacy')->table('operations')->insert([
            [
                'id' => 46,
                'numero_operation' => '74',
                'mois' => '2025-04',
                'type_financement_id' => 7,
                'montant_op' => 0,
                'status_operation' => 'destroyed',
                'borderau_id' => 1,
                'deleted_at' => '2025-04-30 10:00:00',
            ],
            [
                'id' => 121,
                'numero_operation' => '74',
                'mois' => '2025-05',
                'type_financement_id' => 7,
                'montant_op' => 0,
                'status_operation' => 'validated',
                'borderau_id' => 8,
                'deleted_at' => null,
            ],
        ]);
        DB::connection('legacy')->table('borderaus')->insert([
            [
                'id' => 1,
                'numero_borderau' => 'BOR-05',
                'mois' => '2025-04',
                'type_financement_id' => 7,
                'montant_total' => 0,
                'status_borderau' => 'pending',
            ],
            [
                'id' => 8,
                'numero_borderau' => 'BOR-05',
                'mois' => '2025-05',
                'type_financement_id' => 7,
                'montant_total' => 0,
                'status_borderau' => 'pending',
            ],
        ]);

        foreach (['operations', 'bordereaux', 'operations', 'bordereaux'] as $step) {
            $this->artisan('migrate:legacy-data', ['--step' => $step])->assertExitCode(0);
        }

        $this->assertDatabaseHas('ordre_paiements', ['ancien_id' => 46, 'numero' => '74']);
        $this->assertDatabaseHas('ordre_paiements', ['ancien_id' => 121, 'numero' => '74-LEGACY-121']);
        $this->assertDatabaseHas('bordereau_paiements', ['ancien_id' => 1, 'numero' => 'BOR-05']);
        $this->assertDatabaseHas('bordereau_paiements', ['ancien_id' => 8, 'numero' => 'BOR-05-LEGACY-8']);
        $this->assertDatabaseCount('ordre_paiements', 2);
        $this->assertDatabaseCount('bordereau_paiements', 2);
        $this->assertSame(2, DB::table('anomalies_migration')->where('code', 'OP_NUMERO_DUPLIQUE')->count());
        $this->assertSame(2, DB::table('anomalies_migration')->where('code', 'BORDEREAU_NUMERO_DUPLIQUE')->count());
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

    public function test_legacy_dmg_deferred_pointage_keeps_its_period_link_and_reason(): void
    {
        $agence = Agence::factory()->create(['ancien_id' => 15]);
        $source = SourceFinancement::factory()->create(['ancien_id' => 9]);
        $stage = Stage::factory()->create([
            'ancien_id' => 506,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'date_debut' => '2026-04-01',
        ]);
        $auteur = User::factory()->create();

        DB::connection('legacy')->table('pointage_models')->insert([
            'id' => 9101,
            'stagiaire_id' => 506,
            'mois' => '2026-08',
            'status_cip' => 1,
            'status_ca' => 1,
            'status_dmg' => 0,
            'created_at' => '2026-08-25 08:00:00',
            'updated_at' => '2026-08-25 08:00:00',
        ]);
        DB::connection('legacy')->table('paiement_models')->insert([
            'id' => 9102,
            'stagiaire_id' => 506,
            'pointage_id' => 9101,
            'user_id' => 353,
            'mois' => '2026-08',
            'montant' => 45000,
            'status_dmg' => 0,
            'status_ar' => 0,
            'observation' => 'DEMARRAGE NON RECU',
            'created_at' => '2026-08-26 09:30:00',
            'updated_at' => '2026-08-26 09:30:00',
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'pointages'])->assertExitCode(0);
        $this->artisan('migrate:legacy-data', ['--step' => 'paiements'])->assertExitCode(0);
        $this->artisan('migrate:legacy-data', ['--step' => 'paiements'])->assertExitCode(0);

        $pointage = Pointage::where('ancien_id', 9101)->firstOrFail();
        $paiement = Paiement::where('ancien_id', 9102)->firstOrFail();

        $this->assertSame('2026-08', $pointage->periode->code);
        $this->assertSame('AJOURNE_DMG', $paiement->statut);
        $this->assertSame($pointage->id, $paiement->droitPaiement->pointage_id);
        $this->assertDatabaseHas('decisions_paiements', [
            'paiement_id' => $paiement->id,
            'auteur_id' => $auteur->id,
            'decision' => 'AJOURNE_DMG',
            'motif' => 'DEMARRAGE NON RECU',
        ]);
        $this->assertSame(1, DecisionPaiement::where('paiement_id', $paiement->id)->count());
        $this->assertSame($stage->id, $paiement->droitPaiement->pointage->stage_id);
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

        $this->artisan('migrate:legacy-data', ['--step' => 'pointages', '--chunk' => 1])->assertExitCode(0);
        $this->artisan('migrate:legacy-data', ['--step' => 'pointages', '--chunk' => 1])->assertExitCode(0);

        $pointage = Pointage::where('stage_id', $stage->id)->firstOrFail();
        $this->assertSame('DEMARRAGE', $pointage->nature);
        $this->assertSame('2026-08', $pointage->periode->code);
        $this->assertSame(1, Pointage::where('stage_id', $stage->id)->count());
        $this->assertSame(2, VersionPointage::where('pointage_id', $pointage->id)->count());
        $this->assertSame(2, $pointage->fresh()->version_courante);
        $this->assertDatabaseHas('progressions_migration_legacy', [
            'phase' => 'pointages',
            'version_source' => 'gestage-mysql-v2',
            'dernier_id_source' => 7002,
            'statut' => 'TERMINEE',
        ]);

        DB::connection('legacy')->table('pointage_models')->insert([
            'id' => 7003,
            'stagiaire_id' => 503,
            'mois' => '2026-08',
            'commentaire' => 'Ajout après gel de la source',
            'status_dmg' => 1,
            'status_ca' => 1,
            'etape_id' => 13,
            'created_at' => '2026-09-03 08:00:00',
            'updated_at' => '2026-09-03 08:00:00',
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'pointages', '--resume' => true])->assertExitCode(0);
        $this->assertSame(2, VersionPointage::where('pointage_id', $pointage->id)->count());
    }

    public function test_pointage_for_a_stagiaire_who_left_the_program_does_not_create_a_ca_task(): void
    {
        $agence = Agence::factory()->create(['ancien_id' => 21]);
        $source = SourceFinancement::factory()->create(['ancien_id' => 4]);
        $stage = Stage::factory()->create([
            'ancien_id' => 601,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'date_debut' => '2026-06-10',
        ]);

        DB::connection('legacy')->table('pointage_models')->insert([
            'id' => 8001,
            'stagiaire_id' => 601,
            'mois' => '2026-07',
            'status_cip' => 1,
            'status_ca' => 0,
            'status_dmg' => 0,
            'situationstage_id' => 2, // ABANDON
            'created_at' => '2026-07-05 08:00:00',
            'updated_at' => '2026-07-05 08:00:00',
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'pointages', '--chunk' => 1])->assertExitCode(0);

        $pointage = Pointage::where('stage_id', $stage->id)->firstOrFail();
        $instance = InstanceParcours::where('pointage_id', $pointage->id)->firstOrFail();

        // La corbeille reste renseignée pour la traçabilité, mais aucune tâche CA ouverte
        // ne doit être créée : le stagiaire est sorti du dispositif (abandon/suspension/désistement).
        $this->assertSame('ca_validation_pointages', $instance->corbeille_actuelle);
        $this->assertSame(0, TacheParcours::where('instance_parcours_id', $instance->id)
            ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])
            ->count());
        $this->assertDatabaseHas('anomalies_migration', [
            'code' => 'POINTAGE_STAGIAIRE_SORTI_HORS_CORBEILLE_CA',
            'table_source' => 'pointage_models',
            'id_source' => '8001',
        ]);
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

    public function test_presence_backfill_persists_the_missing_payment_idempotently(): void
    {
        $agence = Agence::factory()->create(['ancien_id' => 15]);
        $source = SourceFinancement::factory()->create(['ancien_id' => 9]);
        $periode = Periode::create([
            'code' => '2026-09',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-30',
        ]);
        $stage = Stage::factory()->create([
            'ancien_id' => 505,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'date_debut' => '2026-08-10',
        ]);
        $pointage = Pointage::create([
            'ancien_id' => 7100,
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'nature' => 'PRESENCE',
            'statut' => 'VALIDE',
            'version_courante' => 1,
        ]);
        $definition = DefinitionParcours::create([
            'code' => 'POINTAGE_BACKFILL_TEST',
            'nom' => 'Pointage backfill test',
            'version' => 1,
            'active' => true,
        ]);
        $etape = EtapeParcours::create([
            'definition_parcours_id' => $definition->id,
            'code' => 'DMG_ATTENTE_PAIEMENT_PRESENCE',
            'nom' => 'DMG attente paiement présence',
        ]);
        InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'pointage_id' => $pointage->id,
            'corbeille_actuelle' => 'dmg_attente_paiement_presence',
        ]);
        DB::connection('legacy')->table('pointage_models')->insert([
            'id' => 7100,
            'stagiaire_id' => 505,
            'mois' => '2026-09',
            'date_ca' => '2026-10-02 09:00:00',
            'created_at' => '2026-09-30 08:00:00',
            'updated_at' => '2026-10-02 09:00:00',
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'backfill_presence_payments'])->assertExitCode(0);
        $this->artisan('migrate:legacy-data', ['--step' => 'backfill_presence_payments'])->assertExitCode(0);

        $droit = DroitPaiement::where('pointage_id', $pointage->id)->firstOrFail();
        $this->assertSame(1, DroitPaiement::where('pointage_id', $pointage->id)->count());
        $this->assertSame(1, Paiement::where('droit_paiement_id', $droit->id)->count());
        $this->assertDatabaseHas('paiements', [
            'droit_paiement_id' => $droit->id,
            'statut' => 'A_TRAITER',
            'montant' => 45000,
        ]);
    }

    public function test_update_missing_data_updates_existing_beneficiaires_without_inserting_incomplete_rows(): void
    {
        Beneficiaire::factory()->create([
            'numero_aej' => 'AEJ-0001',
            'numero_tresor_money' => null,
            'numero_wave' => null,
            'type_paiement_id' => null,
        ]);

        Schema::connection('legacy')->create('type_paiements', function (Blueprint $table): void {
            $table->id();
            $table->string('libelle')->nullable();
        });

        Schema::connection('legacy')->table('contrats_pae', function (Blueprint $table): void {
            $table->string('numero_aej')->nullable();
            $table->string('numero_yup')->nullable();
            $table->string('numero_wave')->nullable();
            $table->unsignedBigInteger('type_paiement_id')->nullable();
        });

        DB::connection('legacy')->table('type_paiements')->insert([
            'id' => 7,
            'libelle' => 'Wave',
        ]);

        DB::connection('legacy')->table('contrats_pae')->insert([
            'id' => 1001,
            'numero_aej' => 'AEJ-0001',
            'numero_yup' => '2250100100',
            'numero_wave' => '0177001001',
            'type_paiement_id' => 7,
        ]);

        DB::connection('legacy')->table('contrats_pae')->insert([
            'id' => 1002,
            'numero_aej' => 'AEJ-ABSENT',
            'numero_yup' => '2250100200',
            'numero_wave' => '0177001002',
            'type_paiement_id' => 7,
        ]);

        $this->artisan('migrate:legacy-data', ['--step' => 'update_missing_data'])
            ->assertExitCode(0);

        $beneficiaire = Beneficiaire::where('numero_aej', 'AEJ-0001')->firstOrFail();

        $this->assertSame('2250100100', $beneficiaire->numero_tresor_money);
        $this->assertSame('0177001001', $beneficiaire->numero_wave);
        $this->assertNotNull($beneficiaire->type_paiement_id);
        $this->assertSame(1, Beneficiaire::count());
        $this->assertDatabaseHas('types_paiement', [
            'ancien_id' => 7,
            'nom' => 'Wave',
        ]);
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

    /**
     * `etat_desse` ne vaut que pour un dossier déjà validé par le chef d'agence : sans ce
     * feu vert, le dossier n'est pas soumis à la DESSE et reste sans visa.
     */
    public function test_it_backfills_desse_visas_only_for_dossiers_validated_by_the_agency_head(): void
    {
        Schema::connection('legacy')->table('contrats_pae', function (Blueprint $table): void {
            $table->unsignedTinyInteger('etat_desse')->nullable();
            $table->text('motif_desse')->nullable();
            $table->timestamp('date_desse')->nullable();
            $table->unsignedBigInteger('id_user_desse')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        $attendus = [
            // [ancien_id, etat_chef_agence, etat_desse, visa attendu]
            [3001, 2, 0, VisaDesseEnum::EN_ATTENTE],
            [3002, 2, 1, VisaDesseEnum::REJETE],
            [3003, 2, 2, VisaDesseEnum::VISE],
            // Pas encore validé par le CA : aucun visa, même si `etat_desse` est renseigné.
            [3004, 1, 2, null],
        ];

        foreach ($attendus as [$ancienId, $etatCa, $etatDesse]) {
            DB::connection('legacy')->table('contrats_pae')->insert([
                'id' => $ancienId,
                'etat_chef_agence' => $etatCa,
                'etat_desse' => $etatDesse,
                'motif_desse' => 'Motif historique',
                'date_desse' => '2026-03-15 09:00:00',
            ]);

            Stage::factory()->create(['ancien_id' => $ancienId]);
        }

        $this->artisan('migrate:legacy-data', ['--step' => 'backfill_visa_desse'])
            ->assertExitCode(0);

        foreach ($attendus as [$ancienId, , , $visaAttendu]) {
            $this->assertSame(
                $visaAttendu,
                Stage::where('ancien_id', $ancienId)->firstOrFail()->visa_desse,
                "Visa inattendu pour le contrat legacy {$ancienId}"
            );
        }

        // Le motif n'est conservé que sur les décisions, pas sur l'attente.
        $this->assertNull(Stage::where('ancien_id', 3001)->firstOrFail()->visa_desse_le);
        $this->assertSame('Motif historique', Stage::where('ancien_id', 3002)->firstOrFail()->motif_visa_desse);
    }

    /**
     * Le legacy ne distinguait un renouvellement ajourné que par le trio
     * `etapetraitement_id=2 / etat_chef_agence=1 / active_chef_agence=0` : il doit devenir
     * le `statut` de l'avenant, seul porteur de l'information côté Next.
     */
    public function test_it_flags_adjourned_renewals_on_the_created_amendment(): void
    {
        Schema::connection('legacy')->table('contrats_pae', function (Blueprint $table): void {
            $table->unsignedBigInteger('etatrenouvellement_id')->nullable();
            $table->unsignedBigInteger('etapetraitement_id')->nullable();
            $table->unsignedTinyInteger('active_chef_agence')->nullable();
            $table->date('date_debut_renouv')->nullable();
            $table->date('date_fin_renouv')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        foreach ([
            ['id' => 2001, 'etapetraitement_id' => 2, 'etat_chef_agence' => 1, 'active_chef_agence' => 0],
            ['id' => 2002, 'etapetraitement_id' => 3, 'etat_chef_agence' => 1, 'active_chef_agence' => 1],
        ] as $ligne) {
            DB::connection('legacy')->table('contrats_pae')->insert($ligne + [
                'etatrenouvellement_id' => 1,
                'date_debut_renouv' => '2026-09-01',
                'date_fin_renouv' => '2027-02-28',
            ]);

            $stage = Stage::factory()->create(['ancien_id' => $ligne['id']]);
            Contrat::factory()->create([
                'stage_id' => $stage->id,
                'numero' => 'CTR-'.$ligne['id'],
            ]);
        }

        $this->artisan('migrate:legacy-data', ['--step' => 'backfill_avenants_renouvellement'])
            ->assertExitCode(0);

        $this->assertSame(
            AvenantContrat::STATUT_AJOURNE,
            AvenantContrat::where('numero', 'CTR-2001-R1')->firstOrFail()->statut
        );

        $this->assertSame(
            AvenantContrat::STATUT_VALIDE,
            AvenantContrat::where('numero', 'CTR-2002-R1')->firstOrFail()->statut
        );
    }
}
