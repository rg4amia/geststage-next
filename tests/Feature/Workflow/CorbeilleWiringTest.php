<?php

namespace Tests\Feature\Workflow;

use App\Enums\CorbeilleEnum;
use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Reference\Periode;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CorbeilleWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_corbeilles_financieres_sont_branchees_sur_inertia(): void
    {
        $user = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('administrateur');

        $pages = [
            '/dmg/validation' => ['Dmg/Validation/Index', ['attenteVerification', 'valides']],
            '/dmg/paiements' => ['Dmg/Paiements/Index', ['attenteDemarrage', 'attentePresence', 'dossiers']],
            '/dmg/operations' => ['Dmg/Operations/Index', ['elaborationOp', 'bordereaux', 'fichierCut']],
            '/dmg/rejets' => ['Dmg/Rejets/Index', ['ajournesCB', 'rejetesAC', 'differesAC']],
            '/cb/paiements' => ['Cb/Paiements/Index', ['dossiersControle', 'etatsAjournes']],
            '/agent-comptable/paiements' => ['AgentComptable/Paiements/Index', ['bordereauxAttente', 'ordresRejetes', 'statutPaiements']],
            '/desse/stagiaires' => ['Desse/Stagiaires/Index', ['attenteValidation', 'doublons', 'statistiques']],
            '/daicg/stagiaires' => ['Daicg/Stagiaires/Index', ['validesCA', 'validesDESSE', 'sansContrat']],
            '/cip/suivi' => ['Cip/Suivi/Index', ['differesAC', 'doublonsDESSE', 'renouvellements', 'suspensionsAbandons']],
            '/cip/pointages/pejedec' => ['Cip/Pointages/Pejedec', ['attente', 'effectues', 'ajournesCA', 'ajournesDMG', 'moisManques', 'moisActuel', 'sourceFinancement']],
            '/pejedec/af' => ['Pejedec/Aaf/Index', ['attenteValidation', 'paiementsAjournes', 'correctionsAValider', 'attentePaiement', 'statistiques', 'moisActuel', 'sourceFinancement', 'agences', 'entreprises', 'sourcesFinancement', 'filters']],
            '/pejedec/af/attente-validation' => ['Pejedec/Aaf/AttenteValidation', ['attenteValidation', 'moisActuel', 'sourceFinancement', 'agences', 'entreprises', 'sourcesFinancement', 'filters']],
            '/pejedec/af/paiements-ajournes' => ['Pejedec/Aaf/PaiementsAjournes', ['paiementsAjournes', 'moisActuel', 'sourceFinancement', 'agences', 'entreprises', 'sourcesFinancement', 'filters']],
            '/pejedec/af/corrections-a-valider' => ['Pejedec/Aaf/CorrectionsAValider', ['correctionsAValider', 'moisActuel', 'sourceFinancement', 'agences', 'entreprises', 'sourcesFinancement', 'filters']],
            '/pejedec/af/attente-paiement' => ['Pejedec/Aaf/AttentePaiement', ['attentePaiement', 'moisActuel', 'sourceFinancement', 'agences', 'entreprises', 'sourcesFinancement', 'filters']],
        ];

        foreach ($pages as $uri => [$component, $props]) {
            $this->actingAs($user)
                ->get($uri)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->hasAll($props)
                );
        }
    }

    public function test_actions_desse_branchees_sur_les_transitions_metier(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Role::create(['name' => 'desse', 'guard_name' => 'web']);

        $definition = DefinitionParcours::factory()->create(['code' => 'PAE', 'active' => true]);
        $etape = EtapeParcours::factory()->create([
            'definition_parcours_id' => $definition->id,
            'code' => 'DESSE_ATTENTE',
            'nom' => 'Attente DESSE',
            'code_corbeille' => CorbeilleEnum::DESSE_ATTENTE_VERIFICATION_DMG->value,
            'initiale' => true,
        ]);

        $instanceValider = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => Stage::factory()->create()->id,
            'corbeille_actuelle' => CorbeilleEnum::DESSE_ATTENTE_VERIFICATION_DMG->value,
        ]);

        $this->post("/desse/stagiaires/valider/{$instanceValider->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('instances_parcours', [
            'id' => $instanceValider->id,
            'corbeille_actuelle' => CorbeilleEnum::DAICG_VALIDES_DESSE->value,
        ]);

        $instanceAjourner = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => Stage::factory()->create()->id,
            'corbeille_actuelle' => CorbeilleEnum::DESSE_ATTENTE_VERIFICATION_DMG->value,
        ]);

        $this->post("/desse/stagiaires/ajourner/{$instanceAjourner->id}", [
            'motif' => 'Pièces incomplètes pour vérification.',
        ])->assertRedirect();

        $this->assertDatabaseHas('instances_parcours', [
            'id' => $instanceAjourner->id,
            'corbeille_actuelle' => CorbeilleEnum::DESSE_RETOUR_AGENCE->value,
        ]);

        $instanceDoublon = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => Stage::factory()->create()->id,
            'corbeille_actuelle' => CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER->value,
        ]);

        $this->post("/desse/stagiaires/doublons/{$instanceDoublon->id}/traiter")
            ->assertRedirect();

        $this->assertDatabaseHas('instances_parcours', [
            'id' => $instanceDoublon->id,
            'corbeille_actuelle' => CorbeilleEnum::DESSE_DOUBLONS_TRAITES->value,
        ]);
    }

    public function test_actions_pejedec_aaf_branchees_sur_les_transitions_metier(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $stageValidation = Stage::factory()->create();
        $periodeValidation = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
            'ouverte_pointage' => true,
            'ouverte_paiement' => true,
        ]);

        $pointageSoumis = Pointage::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stageValidation->id,
            'periode_id' => $periodeValidation->id,
            'nature' => 'MENSUEL',
            'statut' => 'SOUMIS',
            'version_courante' => 1,
            'version_verrouillage' => 0,
        ]);

        VersionPointage::create([
            'pointage_id' => $pointageSoumis->id,
            'saisi_par_id' => $user->id,
            'numero_version' => 1,
            'presence' => 'PRESENT',
            'jours_presents' => 12,
            'jours_absents' => 8,
            'observation' => 'Soumission PEJEDEC',
        ]);

        $this->post("/pejedec/af/pointages/{$pointageSoumis->id}/valider")
            ->assertRedirect();

        $this->assertDatabaseHas('pointages', [
            'id' => $pointageSoumis->id,
            'statut' => 'VALIDE',
        ]);

        $this->assertDatabaseHas('droits_paiement', [
            'stage_id' => $stageValidation->id,
            'pointage_id' => $pointageSoumis->id,
            'periode_id' => $periodeValidation->id,
            'statut' => 'OUVERT',
        ]);

        $stageCorrection = Stage::factory()->create();
        $pointageCorrige = Pointage::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stageCorrection->id,
            'periode_id' => $periodeValidation->id,
            'nature' => 'MENSUEL',
            'statut' => 'CORRIGE_CIP',
            'version_courante' => 1,
            'version_verrouillage' => 0,
        ]);

        VersionPointage::create([
            'pointage_id' => $pointageCorrige->id,
            'saisi_par_id' => $user->id,
            'numero_version' => 1,
            'presence' => 'PRESENT',
            'jours_presents' => 10,
            'jours_absents' => 10,
            'observation' => 'Correction CIP',
        ]);

        $this->post("/pejedec/af/pointages/{$pointageCorrige->id}/valider-correction")
            ->assertRedirect();

        $this->assertDatabaseHas('pointages', [
            'id' => $pointageCorrige->id,
            'statut' => 'VALIDE',
        ]);

        $this->assertDatabaseHas('decisions_pointages', [
            'pointage_id' => $pointageCorrige->id,
            'decision' => 'VALIDE_AAF',
        ]);

        $stagePaiement = Stage::factory()->create();
        $droitPaiement = DroitPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stagePaiement->id,
            'pointage_id' => null,
            'periode_id' => $periodeValidation->id,
            'source_financement_id' => $stagePaiement->source_financement_id,
            'nature' => 'PRESENCE',
            'montant' => 12500,
            'statut' => 'OUVERT',
        ]);

        $this->post("/pejedec/af/droits-paiement/{$droitPaiement->id}/generer")
            ->assertRedirect();

        $this->assertDatabaseHas('paiements', [
            'droit_paiement_id' => $droitPaiement->id,
            'statut' => 'A_TRAITER',
        ]);
    }
}
