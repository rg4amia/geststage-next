<?php

namespace Tests\Feature\ChefAgence;

use App\Domain\Contract\Services\RenouvellementService;
use App\Models\Contract\AvenantContrat;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Reference\SituationStage;
use App\Models\Reference\TypeStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Validation des renouvellements par le Chef d'Agence.
 *
 * Portage de `RenouvellementContratController@attenteValidationByChefAgence` (legacy)
 * et du workflow CIP → Chef d'Agence → CIP en cas d'ajournement.
 */
class RenouvellementValidationTest extends TestCase
{
    use RefreshDatabase;

    private TypeStage $qualification;

    private int $numeroCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-04');

        // Rôles Spatie
        Role::create(['name' => 'administrateur', 'guard_name' => 'web']);
        Role::create(['name' => 'chef_agence', 'guard_name' => 'web']);
        Role::create(['name' => 'CIP', 'guard_name' => 'web']);

        foreach ([
            [SituationStage::CODE_EN_COURS, 'EN COURS'],
            [SituationStage::CODE_FIN_DE_STAGE, 'FIN DE STAGE'],
        ] as [$code, $nom]) {
            DB::table('situations_stage')->insert([
                'code' => $code,
                'nom' => $nom,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->qualification = TypeStage::factory()->create([
            'code' => TypeStage::CODE_QUALIFICATION,
            'nom' => 'STAGE DE QUALIFICATION',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Crée un stage avec contrat et avenant au statut ATTENTE_CA.
     */
    private function stageAvecAvenantEnAttente(array $attributsStage = [], array $attributsAvenant = []): array
    {
        $stage = Stage::factory()->create(array_merge([
            'type_stage_id' => $this->qualification->id,
            'situation_stage' => SituationStage::CODE_EN_COURS,
            'date_fin_prevue' => '2026-08-31',
        ], $attributsStage));

        $contrat = Contrat::factory()->create([
            'stage_id' => $stage->id,
            'date_fin' => $stage->date_fin_prevue,
        ]);

        $this->numeroCounter++;

        $avenant = AvenantContrat::create(array_merge([
            'contrat_id' => $contrat->id,
            'numero' => "CTR-R-{$this->numeroCounter}",
            'date_effet' => '2026-09-01',
            'nouvelle_date_fin' => '2027-02-28',
            'motif' => 'Renouvellement de contrat',
            'statut' => AvenantContrat::STATUT_ATTENTE_CA,
            'propose_par_id' => User::factory()->create()->id,
            'propose_le' => now(),
        ], $attributsAvenant));

        return ['stage' => $stage->fresh(), 'contrat' => $contrat, 'avenant' => $avenant->fresh()];
    }

    /**
     * Crée un stage avec contrat et avenant AJOURNE.
     */
    private function stageAvecAvenantAjourne(array $attributsStage = [], array $attributsAvenant = []): array
    {
        $stage = Stage::factory()->create(array_merge([
            'type_stage_id' => $this->qualification->id,
            'situation_stage' => SituationStage::CODE_EN_COURS,
        ], $attributsStage));

        $contrat = Contrat::factory()->create([
            'stage_id' => $stage->id,
            'date_fin' => $stage->date_fin_prevue,
        ]);

        $this->numeroCounter++;

        $avenant = AvenantContrat::create(array_merge([
            'contrat_id' => $contrat->id,
            'numero' => "CTR-A-{$this->numeroCounter}",
            'date_effet' => '2027-01-01',
            'motif' => 'Renouvellement',
            'statut' => AvenantContrat::STATUT_AJOURNE,
            'motif_ajournement' => 'Pièces manquantes',
            'decide_le' => now()->subDay(),
            'decideur_id' => User::factory()->create()->id,
        ], $attributsAvenant));

        return ['stage' => $stage->fresh(), 'contrat' => $contrat, 'avenant' => $avenant->fresh()];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Chef d'Agence : filtres périmètre agence
    // ──────────────────────────────────────────────────────────────────────

    public function test_le_chef_agence_ne_voit_que_les_avenants_de_son_agence(): void
    {
        $mien = $this->stageAvecAvenantEnAttente();
        $autre = $this->stageAvecAvenantEnAttente();

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($mien['stage']->agence_id);

        $this->actingAs($chef);

        $service = app(RenouvellementService::class);
        $ids = $service->chefAgenceValidationQuery()->pluck('id')->all();

        $this->assertSame([$mien['stage']->id], $ids);
    }

    public function test_un_administrateur_voit_tous_les_avenants_sans_restriction(): void
    {
        $mien = $this->stageAvecAvenantEnAttente();
        $autre = $this->stageAvecAvenantEnAttente();

        $admin = User::factory()->create();
        $admin->syncRoles(['administrateur']);

        $this->actingAs($admin);

        $service = app(RenouvellementService::class);
        $ids = $service->chefAgenceValidationQuery()->pluck('id')->all();

        $this->assertContains($mien['stage']->id, $ids);
        $this->assertContains($autre['stage']->id, $ids);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Validation unitaire
    // ──────────────────────────────────────────────────────────────────────

    public function test_valider_par_chef_agence_met_statut_valide_et_decideur(): void
    {
        $donnee = $this->stageAvecAvenantEnAttente();
        $avenant = $donnee['avenant'];

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($donnee['stage']->agence_id);

        $this->actingAs($chef);

        app(RenouvellementService::class)->validerParChefAgence($avenant, $chef->id);

        $avenant->refresh();

        $this->assertSame(AvenantContrat::STATUT_VALIDE, $avenant->statut);
        $this->assertSame($chef->id, $avenant->decideur_id);
        $this->assertNotNull($avenant->decide_le);
        $this->assertNull($avenant->motif_ajournement);
    }

    public function test_valider_par_chef_agence_via_http(): void
    {
        $donnee = $this->stageAvecAvenantEnAttente();

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($donnee['stage']->agence_id);

        $this->actingAs($chef);

        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/valider")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('avenants_contrats', [
            'id' => $donnee['avenant']->id,
            'statut' => AvenantContrat::STATUT_VALIDE,
            'decideur_id' => $chef->id,
        ]);
    }

    public function test_valider_un_avenant_deja_valide_rejette(): void
    {
        $donnee = $this->stageAvecAvenantEnAttente([
            'date_fin_prevue' => '2026-12-31',
        ], [
            'statut' => AvenantContrat::STATUT_VALIDE,
        ]);

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($donnee['stage']->agence_id);

        $this->actingAs($chef);

        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/valider")
            ->assertRedirect();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Ajournement unitaire
    // ──────────────────────────────────────────────────────────────────────

    public function test_ajourner_par_chef_agence_met_statut_ajourne_avec_motif(): void
    {
        $donnee = $this->stageAvecAvenantEnAttente();
        $avenant = $donnee['avenant'];

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($donnee['stage']->agence_id);

        $this->actingAs($chef);

        app(RenouvellementService::class)->ajournerParChefAgence(
            $avenant,
            'Document incomplet, veuillez refaire le contrat',
            $chef->id
        );

        $avenant->refresh();

        $this->assertSame(AvenantContrat::STATUT_AJOURNE, $avenant->statut);
        $this->assertSame('Document incomplet, veuillez refaire le contrat', $avenant->motif_ajournement);
        $this->assertSame($chef->id, $avenant->decideur_id);
        $this->assertNotNull($avenant->decide_le);
    }

    public function test_ajourner_via_http_requiert_observation(): void
    {
        $donnee = $this->stageAvecAvenantEnAttente();

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($donnee['stage']->agence_id);

        $this->actingAs($chef);

        // Sans observation
        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/ajourner", [
            'observation' => '',
        ])->assertSessionHasErrors('observation');

        // Observation trop courte
        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/ajourner", [
            'observation' => 'OK',
        ])->assertSessionHasErrors('observation');

        // Observation valide
        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/ajourner", [
            'observation' => 'Le contrat doit être retourné signé par l\'entreprise.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('avenants_contrats', [
            'id' => $donnee['avenant']->id,
            'statut' => AvenantContrat::STATUT_AJOURNE,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Renvoi CIP (cycle complet)
    // ──────────────────────────────────────────────────────────────────────

    public function test_cip_renvoyer_un_ajournement_remet_attente_ca(): void
    {
        $donnee = $this->stageAvecAvenantAjourne();

        $cip = User::factory()->create();
        $cip->syncRoles(['CIP']);

        $this->actingAs($cip);

        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/renvoyer")
            ->assertRedirect()
            ->assertSessionHas('success');

        $donnee['avenant']->refresh();

        $this->assertSame(AvenantContrat::STATUT_ATTENTE_CA, $donnee['avenant']->statut);
        $this->assertNull($donnee['avenant']->motif_ajournement);
        $this->assertNull($donnee['avenant']->decide_le);
        $this->assertNull($donnee['avenant']->decideur_id);
    }

    public function test_renvoyer_un_avenant_non_ajourne_rejette(): void
    {
        $donnee = $this->stageAvecAvenantEnAttente();

        $this->actingAs(User::factory()->create());

        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/renvoyer")
            ->assertRedirect();
    }

    public function test_apres_renvoi_l_avenant_reapparait_dans_la_corbeille_ca(): void
    {
        $donnee = $this->stageAvecAvenantAjourne();

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($donnee['stage']->agence_id);

        $this->actingAs($chef);

        // Le chef d'agence ne voit pas les ajournés dans sa corbeille
        $service = app(RenouvellementService::class);
        $this->assertSame(0, $service->chefAgenceValidationQuery()->count());
        $this->assertSame(1, $service->ajourneQuery()->count());

        // Le CIP renvoie au chef d'agence
        $cip = User::factory()->create();
        $cip->syncRoles(['CIP']);
        $this->actingAs($cip);
        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/renvoyer")->assertRedirect();

        // Le chef d'agence revoit l'avenant
        $this->actingAs($chef);
        $this->assertSame(1, $service->chefAgenceValidationQuery()->count());
        $this->assertSame(0, $service->ajourneQuery()->count());
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Périmètre : un chef agence ne peut agir que sur son périmètre
    // ──────────────────────────────────────────────────────────────────────

    public function test_chef_agence_ne_peut_pas_valider_un_avenant_du_periodre_d_un_autre(): void
    {
        $autre = $this->stageAvecAvenantEnAttente();

        $autreAgence = \App\Models\Reference\Agence::factory()->create();
        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        // Le chef a un périmètre sur UNE AUTRE agence, pas celle du stage
        $chef->perimetresAgences()->attach($autreAgence->id);

        $this->actingAs($chef);

        $this->post("/cip/renouvellements/avenant/{$autre['avenant']->id}/valider")
            ->assertStatus(403);
    }

    public function test_chef_agence_ne_peut_pas_ajourner_un_avenant_du_periodre_d_un_autre(): void
    {
        $autre = $this->stageAvecAvenantEnAttente();

        $autreAgence = \App\Models\Reference\Agence::factory()->create();
        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($autreAgence->id);

        $this->actingAs($chef);

        $this->post("/cip/renouvellements/avenant/{$autre['avenant']->id}/ajourner", [
            'observation' => 'Observation de test pour refus',
        ])->assertStatus(403);
    }

    public function test_un_utilisateur_sans_role_chef_agence_ne_peut_pas_valider(): void
    {
        $donnee = $this->stageAvecAvenantEnAttente();

        $cip = User::factory()->create();
        $cip->syncRoles(['CIP']);

        $this->actingAs($cip);

        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/valider")
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Actions groupées
    // ──────────────────────────────────────────────────────────────────────

    public function test_valider_groupe_valide_plusieurs_avenants_en_un_coup(): void
    {
        $a = $this->stageAvecAvenantEnAttente();
        $b = $this->stageAvecAvenantEnAttente();

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach([
            $a['stage']->agence_id,
            $b['stage']->agence_id,
        ]);

        $this->actingAs($chef);

        $this->post('/cip/renouvellements/avenants/valider-groupe', [
            'avenant_ids' => [$a['avenant']->id, $b['avenant']->id],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('avenants_contrats', ['id' => $a['avenant']->id, 'statut' => AvenantContrat::STATUT_VALIDE]);
        $this->assertDatabaseHas('avenants_contrats', ['id' => $b['avenant']->id, 'statut' => AvenantContrat::STATUT_VALIDE]);
    }

    public function test_ajourner_groupe_ajourne_plusieurs_avenants_avec_motif(): void
    {
        $a = $this->stageAvecAvenantEnAttente();
        $b = $this->stageAvecAvenantEnAttente();

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach([
            $a['stage']->agence_id,
            $b['stage']->agence_id,
        ]);

        $this->actingAs($chef);

        $this->post('/cip/renouvellements/avenants/ajourner-groupe', [
            'avenant_ids' => [$a['avenant']->id, $b['avenant']->id],
            'observation' => 'Documents manquants pour les deux dossiers',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('avenants_contrats', ['id' => $a['avenant']->id, 'statut' => AvenantContrat::STATUT_AJOURNE]);
        $this->assertDatabaseHas('avenants_contrats', ['id' => $b['avenant']->id, 'statut' => AvenantContrat::STATUT_AJOURNE]);
    }

    public function test_ajourner_groupe_requiert_observation(): void
    {
        $donnee = $this->stageAvecAvenantEnAttente();

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($donnee['stage']->agence_id);

        $this->actingAs($chef);

        $this->post('/cip/renouvellements/avenants/ajourner-groupe', [
            'avenant_ids' => [$donnee['avenant']->id],
            'observation' => '',
        ])->assertSessionHasErrors('observation');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Onglet et écran
    // ──────────────────────────────────────────────────────────────────────

    public function test_l_ecran_expose_l_onglet_chef_validation_si_peut_valider_ca(): void
    {
        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);

        $this->actingAs($chef);

        $this->get('/cip/renouvellements?onglet=chef_validation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cip/Renouvellements/Index')
                ->where('onglet', 'chef_validation')
                ->where('peutValiderCa', true)
            );
    }

    public function test_l_ecran_redirige_vers_attente_si_chef_validation_sans_droit(): void
    {
        // Créer d'abord un autre user pour que le CIP n'ait pas l'ID 1
        User::factory()->create();

        $cip = User::factory()->create();
        $cip->syncRoles(['CIP']);

        $this->actingAs($cip);

        $this->get('/cip/renouvellements?onglet=chef_validation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('onglet', 'attente')
                ->where('peutValiderCa', false)
            );
    }

    public function test_le_compteur_chef_validation_refleter_les_avenants_en_attente(): void
    {
        $a = $this->stageAvecAvenantEnAttente();
        $b = $this->stageAvecAvenantEnAttente();

        // Un ajourné qui ne compte pas dans chef_validation
        $this->stageAvecAvenantAjourne();

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        // Le chef voit les 2 stages en attente + l'ajourné dans sa corbeille ajourné
        $chef->perimetresAgences()->attach([
            $a['stage']->agence_id,
            $b['stage']->agence_id,
        ]);

        $this->actingAs($chef);

        $this->get('/cip/renouvellements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('compteurs.chef_validation', 2)
            );
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Redirection / alias
    // ──────────────────────────────────────────────────────────────────────

    public function test_la_route_renouvellement_redirige_vers_renouvellements(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cip/renouvellement')
            ->assertRedirect('/cip/renouvellements');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Empêcher un avenant déjà validé d'être modifié
    // ──────────────────────────────────────────────────────────────────────

    public function test_valider_un_avenant_deja_ajourne_rejette(): void
    {
        $donnee = $this->stageAvecAvenantAjourne();

        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($donnee['stage']->agence_id);

        $this->actingAs($chef);

        $this->post("/cip/renouvellements/avenant/{$donnee['avenant']->id}/valider")
            ->assertRedirect();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Cycles complets CIP → CA → CIP → CA
    // ──────────────────────────────────────────────────────────────────────

    public function test_cycle_complet_renouvellement_validation(): void
    {
        // 1. Le stage arrive à terme
        $stage = Stage::factory()->create([
            'type_stage_id' => $this->qualification->id,
            'situation_stage' => SituationStage::CODE_EN_COURS,
            'date_fin_prevue' => '2026-08-31',
        ]);

        $contrat = Contrat::factory()->create([
            'stage_id' => $stage->id,
            'date_fin' => '2026-08-31',
        ]);

        // 2. Le CIP propose le renouvellement
        $cip = User::factory()->create();
        $cip->syncRoles(['CIP']);
        $this->actingAs($cip);

        $service = app(RenouvellementService::class);
        $resultat = $service->renouveler($stage->fresh(), 6, 'Besoin de prolongation', null, null, null, null, $cip->id);

        $this->assertSame(AvenantContrat::STATUT_ATTENTE_CA, AvenantContrat::first()->statut);

        // 3. Le chef d'agence valide
        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($stage->agence_id);

        $this->actingAs($chef);

        $avenant = AvenantContrat::first();
        $service->validerParChefAgence($avenant, $chef->id);

        $this->assertSame(AvenantContrat::STATUT_VALIDE, $avenant->fresh()->statut);

        // 4. Le stage a désormais une nouvelle date de fin
        $stage->refresh();
        $this->assertSame('2027-02-28', $stage->date_fin_prevue->format('Y-m-d'));
    }

    public function test_cycle_complet_renouvellement_ajournement_reenvoi(): void
    {
        // 1. Le stage arrive à terme
        $stage = Stage::factory()->create([
            'type_stage_id' => $this->qualification->id,
            'situation_stage' => SituationStage::CODE_EN_COURS,
            'date_fin_prevue' => '2026-08-31',
        ]);

        Contrat::factory()->create([
            'stage_id' => $stage->id,
            'date_fin' => '2026-08-31',
        ]);

        // 2. Le CIP propose le renouvellement
        $cip = User::factory()->create();
        $cip->syncRoles(['CIP']);
        $this->actingAs($cip);

        $service = app(RenouvellementService::class);
        $service->renouveler($stage->fresh(), 3, 'Besoin de prolongation', null, null, null, null, $cip->id);

        // 3. Le chef d'agence ajourne
        $chef = User::factory()->create();
        $chef->syncRoles(['chef_agence']);
        $chef->perimetresAgences()->attach($stage->agence_id);
        $this->actingAs($chef);

        $avenant = AvenantContrat::first();
        $service->ajournerParChefAgence($avenant, 'Contrat mal signé, à refaire', $chef->id);

        $this->assertSame(AvenantContrat::STATUT_AJOURNE, $avenant->fresh()->statut);
        $this->assertSame('Contrat mal signé, à refaire', $avenant->fresh()->motif_ajournement);

        // 4. Le CIP corrige et renvoie
        $this->actingAs($cip);
        $this->post("/cip/renouvellements/avenant/{$avenant->id}/renvoyer")->assertRedirect();

        $avenant->refresh();
        $this->assertSame(AvenantContrat::STATUT_ATTENTE_CA, $avenant->statut);
        $this->assertNull($avenant->motif_ajournement);

        // 5. Le chef d'agence valide这一次
        $this->actingAs($chef);
        $service->validerParChefAgence($avenant, $chef->id);

        $this->assertSame(AvenantContrat::STATUT_VALIDE, $avenant->fresh()->statut);
    }
}
