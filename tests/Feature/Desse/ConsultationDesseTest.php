<?php

namespace Tests\Feature\Desse;

use App\Enums\CorbeilleEnum;
use App\Jobs\ExporterSupervisionJob;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Listes de consultation DESSE : registre des bénéficiaires (legacy
 * `desse/beneficiaire/index`) et stagiaires sans contrat (legacy
 * `desse/stagiaire-sans-contrat`, condition `avis_contrat = 0`).
 */
class ConsultationDesseTest extends TestCase
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

    public function test_la_desse_consulte_le_registre_des_beneficiaires(): void
    {
        $stage = Stage::factory()->create();
        Stage::factory()->count(30)->create();

        $this->actingAs($this->desse)
            ->get('/desse/beneficiaires')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desse/Consultation/Index')
                ->where('liste', 'beneficiaires')
                ->where('stages.total', 31)
                ->count('stages.data', 25)
                ->where('stages.data.0.id', $stage->id) // tri created_at desc
            );
    }

    public function test_les_stagiaires_sans_contrat_excluent_ceux_avec_contrat(): void
    {
        $sansContrat = Stage::factory()->create();
        $avecContrat = Stage::factory()->create();
        Contrat::factory()->create(['stage_id' => $avecContrat->id]);

        $this->actingAs($this->desse)
            ->get('/desse/stagiaires-sans-contrat')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('liste', 'stagiaires-sans-contrat')
                ->count('stages.data', 1)
                ->where('stages.data.0.id', $sansContrat->id)
            );

        // Le contrat supprimé (soft delete) ne remet pas le stage dans la liste.
        $avecContrat->contrats->first()->delete();

        $this->actingAs($this->desse)
            ->get('/desse/stagiaires-sans-contrat')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('stages.data', 2));
    }

    public function test_une_liste_inconnue_renvoie_404(): void
    {
        $this->actingAs($this->desse)
            ->get('/desse/autre-chose')
            ->assertNotFound();

        $this->get('/desse/autre-chose/export')->assertNotFound();
        $this->post('/desse/autre-chose/export')->assertNotFound();
    }

    public function test_les_filtres_sans_contrat_sont_appliques(): void
    {
        $agence = Agence::factory()->create();
        $cible = Stage::factory()->create(['agence_id' => $agence->id]);
        Stage::factory()->create();

        $this->actingAs($this->desse)
            ->get('/desse/stagiaires-sans-contrat?agence_id='.$agence->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('stages.data', 1)
                ->where('stages.data.0.id', $cible->id)
            );

        // Recherche par nom de bénéficiaire.
        $beneficiaire = Beneficiaire::factory()->create(['nom' => 'Kouassi']);
        $parNom = Stage::factory()->create(['beneficiaire_id' => $beneficiaire->id]);

        $this->get('/desse/stagiaires-sans-contrat?recherche=kouassi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('stages.data', 1)
                ->where('stages.data.0.id', $parNom->id)
            );
    }

    public function test_le_filtre_etape_lit_la_corbeille_courante(): void
    {
        $enCorbeille = Stage::factory()->create();
        Stage::factory()->create();

        $this->instanceParcours($enCorbeille, CorbeilleEnum::DESSE_SUIVI_ENREGISTRES);

        $this->actingAs($this->desse)
            ->get('/desse/beneficiaires?etape_id='.CorbeilleEnum::DESSE_SUIVI_ENREGISTRES->value)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('stages.data', 1)
                ->where('stages.data.0.id', $enCorbeille->id)
            );
    }

    public function test_le_perimetre_d_agence_limite_le_chef_d_agence_mais_pas_la_desse(): void
    {
        $agence = Agence::factory()->create();
        $stageAgence = Stage::factory()->create(['agence_id' => $agence->id]);
        Stage::factory()->create();

        $chef = User::factory()->create();
        $chef->assignRole('chef_agence');
        $chef->perimetresAgences()->attach($agence->id);

        $this->actingAs($chef)
            ->get('/desse/stagiaires-sans-contrat')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('stages.data', 1)
                ->where('stages.data.0.id', $stageAgence->id)
            );

        // Vision nationale : la DESSE voit les deux stages.
        $this->actingAs($this->desse)
            ->get('/desse/stagiaires-sans-contrat')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('stages.data', 2));
    }

    public function test_un_utilisateur_sans_habilitation_n_accede_pas_aux_listes(): void
    {
        $intrus = User::factory()->create();

        $this->actingAs($intrus)
            ->get('/desse/beneficiaires')
            ->assertForbidden();

        $this->actingAs($intrus)
            ->get('/desse/stagiaires-sans-contrat')
            ->assertForbidden();
    }

    public function test_l_export_csv_synchrone_reprend_les_lignes_filtrees(): void
    {
        $stage = Stage::factory()->create();
        $avecContrat = Stage::factory()->create();
        Contrat::factory()->create(['stage_id' => $avecContrat->id]);

        $reponse = $this->actingAs($this->desse)
            ->get('/desse/stagiaires-sans-contrat/export');

        $reponse->assertOk();

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString('N° AEJ', $contenu);
        $this->assertStringContainsString($stage->beneficiaire->nom, $contenu);
        $this->assertStringNotContainsString($avecContrat->beneficiaire->nom, $contenu);
    }

    public function test_l_export_volumineux_passe_par_un_batch_puis_est_telechargeable(): void
    {
        Bus::fake();

        Stage::factory()->create();

        $this->actingAs($this->desse)
            ->post('/desse/beneficiaires/export')
            ->assertOk()
            ->assertJsonStructure(['batch_id']);

        Bus::assertBatched(fn ($batch) => $batch->jobs->first() instanceof ExporterSupervisionJob);

        // Un identifiant de batch inconnu ne doit jamais servir de fichier.
        $this->get('/desse/beneficiaires/export/inconnu/download')->assertNotFound();
        $this->get('/desse/beneficiaires/export/inconnu/progress')->assertNotFound();
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
