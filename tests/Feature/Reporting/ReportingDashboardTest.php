<?php

namespace Tests\Feature\Reporting;

use App\Domain\Reporting\Services\ReportingDashboardService;
use App\Models\Attendance\Pointage;
use App\Models\Audit\JournalAudit;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportingDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_tableau_de_bord_reporting_est_branches_sur_inertia(): void
    {
        Role::create(['name' => 'administrateur', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('administrateur');

        $periode = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
            'ouverte_pointage' => true,
            'ouverte_paiement' => true,
        ]);

        $source = SourceFinancement::create([
            'code' => 'PEJEDEC',
            'nom' => 'PEJEDEC',
            'description' => 'Programme PEJEDEC',
            'actif' => true,
        ]);

        $stage = Stage::factory()->create([
            'source_financement_id' => $source->id,
        ]);

        $pointage = Pointage::create([
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'nature' => 'MENSUEL',
            'statut' => 'SOUMIS',
            'version_courante' => 1,
            'version_verrouillage' => 0,
        ]);

        $droitPaiement = DroitPaiement::create([
            'stage_id' => $stage->id,
            'pointage_id' => $pointage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $source->id,
            'nature' => 'PRESENCE',
            'montant' => 12500,
            'statut' => 'OUVERT',
        ]);

        Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'droit_paiement_id' => $droitPaiement?->id,
            'compte_paiement_beneficiaire_id' => null,
            'montant' => 12500,
            'statut' => 'A_TRAITER',
            'corbeille_actuelle' => null,
            'reference_externe' => null,
            'version_verrouillage' => 0,
        ]);

        JournalAudit::create([
            'user_id' => $user->id,
            'action' => 'created',
            'modele_type' => Stage::class,
            'modele_id' => $stage->id,
            'anciennes_donnees' => null,
            'nouvelles_donnees' => ['statut' => 'SOUMIS'],
            'adresse_ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($user)
            ->get('/reporting?mois=2026-08&source_financement_id='.$source->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reporting/Index')
                ->where('statistiques.pointages_attente', 1)
                ->where('statistiques.droits_ouverts', 1)
                ->where('statistiques.paiements_a_traiter', 1)
                ->has('sourcesFinancement', 1)
                ->has('sourceVariants', 5)
                ->has('indicateursBi', 18)
                ->has('graphiques', 9)
                ->where('indicateursBi.0.table', 'stages')
                ->where('indicateursBi.0.export', true)
                ->where('recapPaiements.resume.paiements_en_cours.valeur', 1)
                ->missing('apiToken')
                ->missing('token')
                ->has('journalActivite')
                ->has('alertes')
            );
    }

    public function test_acces_reporting_exige_authentification_et_permission(): void
    {
        $this->get('/reporting')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get('/reporting')
            ->assertForbidden();
    }

    public function test_tous_les_roles_metier_recus_disposent_de_voir_reporting(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (['administrateur', 'cip', 'chef_agence', 'desse', 'daicg', 'dmg', 'cb', 'agent_comptable', 'pejedec', 'aaf'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->assertTrue($user->can('voir_reporting'), $role);
        }
    }

    public function test_variante_paps_gouv_utilise_le_code_reel_et_filtre_tous_les_agregats(): void
    {
        Role::create(['name' => 'administrateur', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('administrateur');
        Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
            'ouverte_pointage' => true,
            'ouverte_paiement' => true,
        ]);
        $paps = SourceFinancement::create(['code' => 'PA_PS_GOUV', 'nom' => 'PA-PS GOUV', 'actif' => true]);
        $budget = SourceFinancement::create(['code' => 'BUDGET_AEJ', 'nom' => 'Budget AEJ', 'actif' => true]);
        SourceFinancement::create(['code' => 'PEJEDEC', 'nom' => 'PEJEDEC', 'actif' => true]);
        SourceFinancement::create(['code' => 'C2D', 'nom' => 'C2D', 'actif' => true]);

        Stage::factory()->create([
            'source_financement_id' => $paps->id,
            'date_debut' => '2026-08-05',
            'date_fin_prevue' => '2026-10-31',
        ]);
        Stage::factory()->create([
            'source_financement_id' => $budget->id,
            'date_debut' => '2026-08-06',
            'date_fin_prevue' => '2026-10-31',
        ]);

        $this->actingAs($user)
            ->get('/reporting?mois=2026-08&source=paps-gouv')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sourceFinancement.id', $paps->id)
                ->where('sourceFinancement.code', 'PA_PS_GOUV')
                ->where('statistiques.stages_total', 1)
                ->where('indicateursBi.0.valeur', 1)
                ->where('graphiques.0.categories.0', 'PA-PS GOUV')
            );
    }

    public function test_reporting_borne_un_cip_a_son_perimetre_agence(): void
    {
        Permission::create(['name' => 'voir_reporting', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'cip', 'guard_name' => 'web']);
        $role->givePermissionTo('voir_reporting');
        $cip = User::factory()->create();
        $cip->assignRole('cip');
        $source = SourceFinancement::create(['code' => 'BUDGET_AEJ', 'nom' => 'Budget AEJ', 'actif' => true]);
        $agency = Agence::factory()->create();
        $otherAgency = Agence::factory()->create();
        $cip->perimetresAgences()->attach($agency->id);

        Stage::factory()->create([
            'agence_id' => $agency->id,
            'source_financement_id' => $source->id,
            'date_debut' => '2026-08-01',
            'date_fin_prevue' => '2026-09-30',
        ]);
        Stage::factory()->create([
            'agence_id' => $otherAgency->id,
            'source_financement_id' => $source->id,
            'date_debut' => '2026-08-01',
            'date_fin_prevue' => '2026-09-30',
        ]);

        $this->actingAs($cip)
            ->get('/reporting?mois=2026-08')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.national', false)
                ->where('scope.agence_ids.0', $agency->id)
                ->where('statistiques.stages_total', 1)
                ->where('graphiques.7.categories.0', $agency->nom)
            );
    }

    public function test_export_csv_reprend_les_filtres_et_reste_prive(): void
    {
        Role::create(['name' => 'administrateur', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('administrateur');
        SourceFinancement::create(['code' => 'C2D', 'nom' => 'C2D', 'actif' => true]);

        $response = $this->actingAs($user)->get('/reporting/export/kpi.csv?mois=2026-08&source=c2d');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
        $content = $response->streamedContent();
        $this->assertStringContainsString('2026-08', $content);
        $this->assertStringContainsString('C2D', $content);
        $this->assertStringContainsString('Bénéficiaires saisis', $content);
    }

    public function test_nombre_de_requetes_ne_croit_pas_avec_le_volume(): void
    {
        Role::create(['name' => 'administrateur', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('administrateur');
        $source = SourceFinancement::create(['code' => 'BUDGET_AEJ', 'nom' => 'Budget AEJ', 'actif' => true]);
        $agency = Agence::factory()->create();
        $type = TypeStage::factory()->create(['code' => TypeStage::CODE_QUALIFICATION]);
        $service = app(ReportingDashboardService::class);
        $filters = ['mois' => '2026-08', 'source_financement_id' => $source->id];

        $service->buildOverview($filters, $user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service->buildOverview($filters, $user);
        $baseline = count(DB::getQueryLog());

        Stage::factory()->count(8)->create([
            'agence_id' => $agency->id,
            'source_financement_id' => $source->id,
            'type_stage_id' => $type->id,
            'date_debut' => '2026-08-01',
            'date_fin_prevue' => '2026-10-31',
        ]);
        DB::flushQueryLog();
        $service->buildOverview($filters, $user);
        $withVolume = count(DB::getQueryLog());

        $this->assertLessThanOrEqual($baseline + 1, $withVolume);
    }
}
