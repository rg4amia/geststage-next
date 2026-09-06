<?php

namespace Tests\Feature\AgenceRegionale;

use App\Domain\Supervision\Services\VisaRegionalService;
use App\Enums\CorbeilleEnum;
use App\Enums\VisaDesseEnum;
use App\Jobs\ExporterVisasRegionauxJob;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\Reference\TypeStage;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Supervision régionale : visa DESSE, dossiers validés par l'agence régionale, extractions
 * de suivi, tableau statistique et pièces justificatives, regroupés sur un seul écran.
 */
class VisaRegionalTest extends TestCase
{
    use RefreshDatabase;

    private User $desse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->desse = User::factory()->create();
        $this->desse->assignRole('desse');
    }

    public function test_les_corbeilles_separent_les_trois_etats_du_visa(): void
    {
        $attente = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);
        $rejete = Stage::factory()->create(['visa_desse' => VisaDesseEnum::REJETE]);
        $vise = Stage::factory()->create(['visa_desse' => VisaDesseEnum::VISE]);

        // Dossier pas encore validé par le chef d'agence : aucun visa attendu.
        Stage::factory()->create(['visa_desse' => null]);

        $this->actingAs($this->desse);

        $service = app(VisaRegionalService::class);

        $this->assertSame([$attente->id], $service->attenteQuery()->pluck('id')->all());
        $this->assertSame([$rejete->id], $service->rejetesQuery()->pluck('id')->all());
        $this->assertSame([$vise->id], $service->visesQuery()->pluck('id')->all());

        $compteurs = $service->compteurs();
        $this->assertSame(1, $compteurs['attente_visa_desse']);
        $this->assertSame(1, $compteurs['rejetes_desse']);
        $this->assertSame(1, $compteurs['vises_desse']);
    }

    public function test_viser_un_dossier_l_horodate_et_trace_son_auteur(): void
    {
        $stage = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);

        $this->actingAs($this->desse)
            ->post("/agence-regionale/visas/{$stage->id}/viser")
            ->assertSessionHas('success');

        $stage->refresh();
        $this->assertSame(VisaDesseEnum::VISE, $stage->visa_desse);
        $this->assertSame($this->desse->id, $stage->visa_desse_par_id);
        $this->assertNotNull($stage->visa_desse_le);
    }

    public function test_le_rejet_exige_un_motif_qui_est_conserve(): void
    {
        $stage = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);

        $this->actingAs($this->desse)
            ->post("/agence-regionale/visas/{$stage->id}/rejeter", ['motif' => ''])
            ->assertSessionHasErrors('motif');

        $this->assertSame(VisaDesseEnum::EN_ATTENTE, $stage->fresh()->visa_desse);

        $this->post("/agence-regionale/visas/{$stage->id}/rejeter", ['motif' => 'Pièce d’identité illisible'])
            ->assertSessionHas('success');

        $stage->refresh();
        $this->assertSame(VisaDesseEnum::REJETE, $stage->visa_desse);
        $this->assertSame('Pièce d’identité illisible', $stage->motif_visa_desse);
    }

    public function test_un_dossier_deja_tranche_ne_peut_pas_etre_vise_de_nouveau(): void
    {
        $stage = Stage::factory()->create(['visa_desse' => VisaDesseEnum::VISE]);

        $this->actingAs($this->desse)
            ->post("/agence-regionale/visas/{$stage->id}/viser")
            ->assertSessionHas('error');
    }

    public function test_remettre_en_attente_efface_le_motif_du_rejet(): void
    {
        $stage = Stage::factory()->create([
            'visa_desse' => VisaDesseEnum::REJETE,
            'motif_visa_desse' => 'Pièces manquantes',
            'visa_desse_le' => now(),
        ]);

        $this->actingAs($this->desse)
            ->post("/agence-regionale/visas/{$stage->id}/remettre-en-attente")
            ->assertSessionHas('success');

        $stage->refresh();
        $this->assertSame(VisaDesseEnum::EN_ATTENTE, $stage->visa_desse);
        $this->assertNull($stage->motif_visa_desse);
        $this->assertNull($stage->visa_desse_le);
    }

    public function test_l_ecran_expose_ses_onglets_et_leurs_compteurs(): void
    {
        $attente = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);
        Stage::factory()->count(2)->create(['visa_desse' => VisaDesseEnum::REJETE]);

        $this->actingAs($this->desse);

        $this->get('/agence-regionale/visas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AgenceRegionale/Visas/Index')
                ->where('onglet', 'attente_visa_desse')
                ->where('compteurs.attente_visa_desse', 1)
                ->where('compteurs.rejetes_desse', 2)
                ->where('compteurs.vises_desse', 0)
                ->where('stages.data.0.id', $attente->id)
            );

        $this->get('/agence-regionale/visas?onglet=rejetes_desse')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('onglet', 'rejetes_desse')->count('stages.data', 2));

        // Un onglet inconnu retombe sur la corbeille par défaut.
        $this->get('/agence-regionale/visas?onglet=inconnu')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('onglet', 'attente_visa_desse'));
    }

    public function test_l_ancienne_url_desse_redirige_en_conservant_les_parametres(): void
    {
        $this->actingAs($this->desse)
            ->get('/desse/visas?onglet=rejetes_desse&agence_id=7')
            ->assertRedirect('/agence-regionale/visas?onglet=rejetes_desse&agence_id=7');
    }

    public function test_les_valides_ar_excluent_les_dossiers_encore_en_amont_du_chef_d_agence(): void
    {
        $valide = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);
        $rouvert = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);
        Stage::factory()->create(['visa_desse' => null]);

        $this->instanceParcours($rouvert, CorbeilleEnum::CIP_MES_STAGIAIRES);
        $this->instanceParcours($valide, CorbeilleEnum::DESSE_SUIVI_VALIDES_AR);

        $this->actingAs($this->desse);

        $this->assertSame(
            [$valide->id],
            app(VisaRegionalService::class)->validesArQuery()->pluck('id')->all()
        );
    }

    public function test_les_onglets_de_suivi_lisent_leurs_corbeilles_de_parcours(): void
    {
        $enregistre = Stage::factory()->create();
        $valideAr = Stage::factory()->create();

        $this->instanceParcours($enregistre, CorbeilleEnum::DESSE_SUIVI_ENREGISTRES);
        $this->instanceParcours($valideAr, CorbeilleEnum::DESSE_SUIVI_VALIDES_AR);

        $this->actingAs($this->desse);

        $service = app(VisaRegionalService::class);

        $this->assertSame([$enregistre->id], $service->suiviEnregistresQuery()->pluck('id')->all());
        $this->assertSame([$valideAr->id], $service->suiviValidesArQuery()->pluck('id')->all());
    }

    public function test_le_tableau_statistique_ventile_par_agence_type_de_stage_et_periode(): void
    {
        $agence = Agence::factory()->create(['nom' => 'Abidjan Plateau']);
        $pae = TypeStage::factory()->create(['code' => TypeStage::CODE_QUALIFICATION]);
        $ecole = TypeStage::factory()->create(['code' => TypeStage::CODE_ECOLE]);

        $vise = Stage::factory()->create([
            'agence_id' => $agence->id,
            'type_stage_id' => $pae->id,
            'visa_desse' => VisaDesseEnum::VISE,
            'date_validation_ar' => '2026-03-10 09:00:00',
        ]);
        Stage::factory()->create([
            'agence_id' => $agence->id,
            'type_stage_id' => $ecole->id,
            'visa_desse' => VisaDesseEnum::EN_ATTENTE,
            'date_validation_ar' => '2026-03-12 09:00:00',
        ]);
        // Hors période : ne doit peser sur aucune colonne.
        Stage::factory()->create([
            'agence_id' => $agence->id,
            'type_stage_id' => $pae->id,
            'visa_desse' => VisaDesseEnum::VISE,
            'date_validation_ar' => '2025-01-05 09:00:00',
        ]);

        $this->instanceParcours($vise, CorbeilleEnum::DMG_ELABORATION_OP);

        $this->actingAs($this->desse);

        $statistiques = app(VisaRegionalService::class)->statistiques('2026-03-01', '2026-03-31');

        $this->assertCount(1, $statistiques['lignes']);

        $ligne = $statistiques['lignes'][0];
        $this->assertSame('Abidjan Plateau', $ligne['agence']);
        $this->assertSame(1, $ligne['inseres_pae']);
        $this->assertSame(1, $ligne['inseres_ecole']);
        $this->assertSame(2, $ligne['inseres_total']);
        $this->assertSame(1, $ligne['desse_pae']);
        $this->assertSame(0, $ligne['desse_ecole']);
        $this->assertSame(1, $ligne['dmg_pae']);
        $this->assertSame(2, $statistiques['totaux']['inseres_total']);
    }

    public function test_l_export_csv_reprend_les_lignes_filtrees(): void
    {
        $stage = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);
        Stage::factory()->create(['visa_desse' => VisaDesseEnum::VISE]);

        $reponse = $this->actingAs($this->desse)
            ->get('/agence-regionale/visas/export?onglet=attente_visa_desse');

        $reponse->assertOk();

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString('N° AEJ', $contenu);
        $this->assertStringContainsString($stage->beneficiaire->nom, $contenu);
        $this->assertSame(2, substr_count(trim($contenu), "\n") + 1, 'En-tête et une seule ligne attendues.');
    }

    public function test_l_export_volumineux_passe_par_un_batch_suivi_puis_telechargeable(): void
    {
        Bus::fake();

        Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);

        $this->actingAs($this->desse)
            ->post('/agence-regionale/visas/export', ['onglet' => 'attente_visa_desse'])
            ->assertOk()
            ->assertJsonStructure(['batch_id']);

        Bus::assertBatched(fn ($batch) => $batch->jobs->first() instanceof ExporterVisasRegionauxJob);

        // Un identifiant de batch inconnu ne doit jamais servir de fichier.
        $this->get('/agence-regionale/visas/export/inconnu/download')->assertNotFound();
        $this->get('/agence-regionale/visas/export/inconnu/progress')->assertNotFound();
    }

    public function test_un_role_sans_habilitation_de_visa_ne_peut_que_consulter(): void
    {
        $daicg = User::factory()->create();
        $daicg->assignRole('daicg');

        $stage = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);

        $this->actingAs($daicg)
            ->get('/agence-regionale/visas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('peutViser', false));

        $this->post("/agence-regionale/visas/{$stage->id}/viser")->assertForbidden();

        $this->assertSame(VisaDesseEnum::EN_ATTENTE, $stage->fresh()->visa_desse);
    }

    public function test_un_utilisateur_sans_permission_n_accede_pas_a_l_ecran(): void
    {
        $intrus = User::factory()->create();

        $this->actingAs($intrus)
            ->get('/agence-regionale/visas')
            ->assertForbidden();
    }

    public function test_une_piece_inconnue_ou_absente_renvoie_404_sans_toucher_au_disque(): void
    {
        $stage = Stage::factory()->create(['visa_desse' => VisaDesseEnum::EN_ATTENTE]);

        $this->actingAs($this->desse);

        // Clé hors du catalogue : refusée avant toute résolution de chemin.
        $this->get("/agence-regionale/visas/{$stage->id}/pieces/../../.env")->assertNotFound();
        $this->get("/agence-regionale/visas/{$stage->id}/pieces/cni")->assertNotFound();

        $this->get("/agence-regionale/visas/{$stage->id}/pieces")
            ->assertOk()
            ->assertJsonPath('stage_id', $stage->id);
    }

    private function instanceParcours(Stage $stage, CorbeilleEnum $corbeille): InstanceParcours
    {
        $definition = DefinitionParcours::factory()->create(['code' => 'PAE-'.$stage->id, 'active' => true]);
        $etape = EtapeParcours::factory()->create([
            'definition_parcours_id' => $definition->id,
            'code' => 'ETAPE-'.$stage->id,
            'code_corbeille' => $corbeille->value,
            'initiale' => true,
        ]);

        return InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => $stage->id,
            'corbeille_actuelle' => $corbeille->value,
        ]);
    }
}
