<?php

namespace Tests\Feature\Workflow;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
}
