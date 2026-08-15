<?php

namespace Tests\Feature\Workflow;

use App\Enums\CorbeilleEnum;
use App\Models\Internship\Stage;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CorbeilleWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_corbeilles_financieres_sont_branchees_sur_inertia(): void
    {
        $user = User::factory()->create();

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
}
