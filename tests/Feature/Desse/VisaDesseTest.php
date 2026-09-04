<?php

namespace Tests\Feature\Desse;

use App\Domain\Supervision\Services\VisaDesseService;
use App\Enums\VisaDesseEnum;
use App\Models\Internship\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visa DESSE des dossiers validés par le chef d'agence, portage des écrans legacy
 * `Validation_Stagiaire_Desse` et `Liste_Stagiaires_Rejetes_Desse`.
 */
class VisaDesseTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_corbeilles_separent_les_trois_etats_du_visa(): void
    {
        $attente = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);
        $rejete = Stage::factory()->create(['visa_desse' => VisaDesseEnum::REJETE]);
        $vise = Stage::factory()->create(['visa_desse' => VisaDesseEnum::VISE]);

        // Dossier pas encore validé par le chef d'agence : aucun visa attendu.
        Stage::factory()->create(['visa_desse' => null]);

        $this->actingAs(User::factory()->create());

        $service = app(VisaDesseService::class);

        $this->assertSame([$attente->id], $service->attenteQuery()->pluck('id')->all());
        $this->assertSame([$rejete->id], $service->rejetesQuery()->pluck('id')->all());
        $this->assertSame([$vise->id], $service->visesQuery()->pluck('id')->all());
        $this->assertSame(
            ['attente' => 1, 'rejetes' => 1, 'vises' => 1],
            $service->compteurs()
        );
    }

    public function test_viser_un_dossier_l_horodate_et_trace_son_auteur(): void
    {
        $stage = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/desse/visas/{$stage->id}/viser")
            ->assertSessionHas('success');

        $stage->refresh();
        $this->assertSame(VisaDesseEnum::VISE, $stage->visa_desse);
        $this->assertSame($user->id, $stage->visa_desse_par_id);
        $this->assertNotNull($stage->visa_desse_le);
    }

    public function test_le_rejet_exige_un_motif_qui_est_conserve(): void
    {
        $stage = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);

        $this->actingAs(User::factory()->create())
            ->post("/desse/visas/{$stage->id}/rejeter", ['motif' => ''])
            ->assertSessionHasErrors('motif');

        $this->assertSame(VisaDesseEnum::EN_ATTENTE, $stage->fresh()->visa_desse);

        $this->post("/desse/visas/{$stage->id}/rejeter", ['motif' => 'Pièce d’identité illisible'])
            ->assertSessionHas('success');

        $stage->refresh();
        $this->assertSame(VisaDesseEnum::REJETE, $stage->visa_desse);
        $this->assertSame('Pièce d’identité illisible', $stage->motif_visa_desse);
    }

    public function test_un_dossier_deja_tranche_ne_peut_pas_etre_vise_de_nouveau(): void
    {
        $stage = Stage::factory()->create(['visa_desse' => VisaDesseEnum::VISE]);

        $this->actingAs(User::factory()->create())
            ->post("/desse/visas/{$stage->id}/viser")
            ->assertSessionHas('error');
    }

    public function test_remettre_en_attente_efface_le_motif_du_rejet(): void
    {
        $stage = Stage::factory()->create([
            'visa_desse' => VisaDesseEnum::REJETE,
            'motif_visa_desse' => 'Pièces manquantes',
            'visa_desse_le' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/desse/visas/{$stage->id}/remettre-en-attente")
            ->assertSessionHas('success');

        $stage->refresh();
        $this->assertSame(VisaDesseEnum::EN_ATTENTE, $stage->visa_desse);
        $this->assertNull($stage->motif_visa_desse);
        $this->assertNull($stage->visa_desse_le);
    }

    public function test_l_ecran_expose_les_trois_onglets_et_leurs_compteurs(): void
    {
        $attente = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);
        Stage::factory()->count(2)->create(['visa_desse' => VisaDesseEnum::REJETE]);

        $this->actingAs(User::factory()->create());

        $this->get('/desse/visas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desse/Visas/Index')
                ->where('onglet', 'attente')
                ->where('compteurs.attente', 1)
                ->where('compteurs.rejetes', 2)
                ->where('compteurs.vises', 0)
                ->where('stages.data.0.id', $attente->id)
            );

        $this->get('/desse/visas?onglet=rejetes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('onglet', 'rejetes')->count('stages.data', 2));

        // Un onglet inconnu retombe sur la corbeille par défaut.
        $this->get('/desse/visas?onglet=inconnu')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('onglet', 'attente'));
    }

    public function test_le_filtre_agence_restreint_la_corbeille(): void
    {
        $cible = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);
        Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);

        $this->actingAs(User::factory()->create());

        $this->assertSame(
            [$cible->id],
            app(VisaDesseService::class)
                ->attenteQuery(['agence_id' => $cible->agence_id])
                ->pluck('id')
                ->all()
        );
    }
}
