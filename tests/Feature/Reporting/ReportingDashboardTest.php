<?php

namespace Tests\Feature\Reporting;

use App\Models\Attendance\Pointage;
use App\Models\Audit\JournalAudit;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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
                ->has('journalActivite')
                ->has('alertes')
            );
    }
}
