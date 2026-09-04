<?php

namespace Tests\Feature\Cip;

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
use Tests\TestCase;

/**
 * Corbeilles CIP du renouvellement (avenant de contrat), portage de
 * `RenouvellementContratController` et `AnticipeRenewController` (legacy).
 */
class RenouvellementTest extends TestCase
{
    use RefreshDatabase;

    private TypeStage $qualification;

    private TypeStage $ecole;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-04');

        foreach ([
            [SituationStage::CODE_EN_COURS, 'EN COURS'],
            [SituationStage::CODE_ABANDON, 'ABANDON'],
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

        $this->ecole = TypeStage::factory()->create([
            'code' => TypeStage::CODE_ECOLE,
            'nom' => 'STAGE ECOLE',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Crée un stage muni de son contrat, comme après la migration legacy.
     */
    private function stageAvecContrat(array $attributs = []): Stage
    {
        $stage = Stage::factory()->create(array_merge([
            'type_stage_id' => $this->qualification->id,
            'situation_stage' => SituationStage::CODE_EN_COURS,
        ], $attributs));

        Contrat::factory()->create([
            'stage_id' => $stage->id,
            'date_fin' => $stage->date_fin_prevue,
        ]);

        return $stage->fresh();
    }

    public function test_la_corbeille_attente_ne_retient_que_les_stages_a_terme_sans_renouvellement_en_cours(): void
    {
        $aRenouveler = $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);

        // Terme atteint, mais un renouvellement attend déjà l'arbitrage du chef d'agence.
        $enCours = $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);
        AvenantContrat::create([
            'contrat_id' => $enCours->contrats()->first()->id,
            'numero' => 'CTR-R1',
            'date_effet' => '2026-09-01',
            'motif' => 'Renouvellement',
            'statut' => AvenantContrat::STATUT_ATTENTE_CA,
        ]);

        // Terme non atteint.
        $this->stageAvecContrat(['date_fin_prevue' => '2026-12-31']);

        // Stage école : jamais renouvelable.
        $this->stageAvecContrat([
            'date_fin_prevue' => '2026-08-31',
            'type_stage_id' => $this->ecole->id,
        ]);

        // Situation incompatible (abandon).
        $this->stageAvecContrat([
            'date_fin_prevue' => '2026-08-31',
            'situation_stage' => SituationStage::CODE_ABANDON,
        ]);

        $this->actingAs(User::factory()->create());

        $this->assertSame(
            [$aRenouveler->id],
            app(RenouvellementService::class)->attenteQuery()->pluck('id')->all()
        );
    }

    /**
     * Un avenant `VALIDE` appartient au passé : il a déjà repoussé le terme. Le stage qui
     * retombe à échéance est donc de nouveau à renouveler, là où le legacy le masquait
     * définitivement via `etatrenouvellement_id != 1`.
     */
    public function test_un_stage_deja_renouvele_revient_en_attente_a_la_nouvelle_echeance(): void
    {
        $stage = $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);
        AvenantContrat::create([
            'contrat_id' => $stage->contrats()->first()->id,
            'numero' => 'CTR-R1',
            'date_effet' => '2026-02-01',
            'nouvelle_date_fin' => '2026-08-31',
            'motif' => 'Renouvellement',
            'statut' => AvenantContrat::STATUT_VALIDE,
        ]);

        // Celui-ci a un renouvellement en cours d'arbitrage : il reste hors corbeille.
        $enCours = $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);
        AvenantContrat::create([
            'contrat_id' => $enCours->contrats()->first()->id,
            'numero' => 'CTR-B-R1',
            'date_effet' => '2026-09-01',
            'motif' => 'Renouvellement',
            'statut' => AvenantContrat::STATUT_ATTENTE_CA,
        ]);

        $this->actingAs(User::factory()->create());

        $service = app(RenouvellementService::class);

        $this->assertSame([$stage->id], $service->attenteQuery()->pluck('id')->all());

        // Et il peut être renouvelé une seconde fois, avec un numéro d'avenant incrémenté.
        $this->assertStringEndsWith('-R2', $service->renouveler($stage->fresh(), 6)['numero']);
    }

    public function test_la_corbeille_anticipee_couvre_les_dix_jours_sans_deborder_du_mois(): void
    {
        $dansHuitJours = $this->stageAvecContrat(['date_fin_prevue' => '2026-09-12']);

        // Au-delà des dix jours d'anticipation.
        $this->stageAvecContrat(['date_fin_prevue' => '2026-09-20']);

        // Terme déjà dépassé : relève de la corbeille « à renouveler ».
        $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);

        $this->actingAs(User::factory()->create());

        $this->assertSame(
            [$dansHuitJours->id],
            app(RenouvellementService::class)->anticipeQuery()->pluck('id')->all()
        );
    }

    public function test_la_corbeille_ajourne_suit_le_statut_de_l_avenant(): void
    {
        $ajourne = $this->stageAvecContrat(['date_fin_prevue' => '2026-12-31']);
        AvenantContrat::create([
            'contrat_id' => $ajourne->contrats()->first()->id,
            'numero' => 'CTR-A-R1',
            'date_effet' => '2027-01-01',
            'motif' => 'Renouvellement',
            'statut' => AvenantContrat::STATUT_AJOURNE,
            'motif_ajournement' => 'Pièces manquantes',
        ]);

        $valide = $this->stageAvecContrat(['date_fin_prevue' => '2026-12-31']);
        AvenantContrat::create([
            'contrat_id' => $valide->contrats()->first()->id,
            'numero' => 'CTR-V-R1',
            'date_effet' => '2027-01-01',
            'motif' => 'Renouvellement',
            'statut' => AvenantContrat::STATUT_VALIDE,
        ]);

        $this->actingAs(User::factory()->create());

        $service = app(RenouvellementService::class);

        $this->assertSame([$ajourne->id], $service->ajourneQuery()->pluck('id')->all());
        $this->assertSame(
            'Pièces manquantes',
            $service->formatLigne($service->ajourneQuery()->first())['motif_ajournement']
        );
    }

    public function test_renouveler_cree_un_avenant_en_attente_et_repousse_le_terme(): void
    {
        $stage = $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);

        $this->actingAs(User::factory()->create());

        $resultat = app(RenouvellementService::class)->renouveler($stage, 6);

        $this->assertSame('2027-02-28', $resultat['nouvelle_date_fin']);

        $stage->refresh();
        $this->assertSame('2027-02-28', $stage->date_fin_prevue->format('Y-m-d'));
        $this->assertSame('STAGE_RENOUVELLE', $stage->statut_stage);

        $this->assertDatabaseHas('avenants_contrats', [
            'contrat_id' => $stage->contrats()->first()->id,
            'statut' => AvenantContrat::STATUT_ATTENTE_CA,
        ]);

        $this->assertSame(
            '2026-09-01',
            AvenantContrat::first()->date_effet->format('Y-m-d')
        );

        // Le stage quitte aussitôt la corbeille « à renouveler ».
        $this->assertSame(0, app(RenouvellementService::class)->attenteQuery()->count());
    }

    public function test_un_renouvellement_en_cours_bloque_une_seconde_demande(): void
    {
        $stage = $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);

        $this->actingAs(User::factory()->create());

        app(RenouvellementService::class)->renouveler($stage->fresh(), 3);

        $this->expectException(\RuntimeException::class);

        app(RenouvellementService::class)->renouveler($stage->fresh(), 3);
    }

    public function test_renvoyer_un_ajournement_le_remet_en_attente_du_chef_agence(): void
    {
        $stage = $this->stageAvecContrat(['date_fin_prevue' => '2026-12-31']);
        $avenant = AvenantContrat::create([
            'contrat_id' => $stage->contrats()->first()->id,
            'numero' => 'CTR-R1',
            'date_effet' => '2027-01-01',
            'motif' => 'Renouvellement',
            'statut' => AvenantContrat::STATUT_AJOURNE,
            'motif_ajournement' => 'Pièces manquantes',
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/cip/renouvellements/avenant/{$avenant->id}/renvoyer")
            ->assertSessionHas('success');

        $avenant->refresh();
        $this->assertSame(AvenantContrat::STATUT_ATTENTE_CA, $avenant->statut);
        $this->assertNull($avenant->motif_ajournement);
        $this->assertSame(0, app(RenouvellementService::class)->ajourneQuery()->count());
    }

    public function test_l_ecran_expose_les_trois_onglets_et_bascule_de_corbeille(): void
    {
        $aRenouveler = $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);
        $anticipe = $this->stageAvecContrat(['date_fin_prevue' => '2026-09-12']);

        $this->actingAs(User::factory()->create());

        $this->get('/cip/renouvellements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cip/Renouvellements/Index')
                ->where('onglet', 'attente')
                ->where('compteurs.attente', 1)
                ->where('compteurs.anticipe', 1)
                ->where('compteurs.ajourne', 0)
                ->where('stages.data.0.id', $aRenouveler->id)
            );

        $this->get('/cip/renouvellements?onglet=anticipe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('onglet', 'anticipe')
                ->where('stages.data.0.id', $anticipe->id)
                ->where('stages.data.0.jours_restants', 8)
            );

        // Un onglet inconnu retombe sur la corbeille par défaut.
        $this->get('/cip/renouvellements?onglet=inconnu')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('onglet', 'attente'));
    }

    public function test_le_cip_ne_voit_que_les_stages_de_son_perimetre_d_agence(): void
    {
        $mien = $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);
        $this->stageAvecContrat(['date_fin_prevue' => '2026-08-31']);

        $user = User::factory()->create();
        $user->perimetresAgences()->attach($mien->agence_id);

        $this->actingAs($user);

        $this->assertSame(
            [$mien->id],
            app(RenouvellementService::class)->attenteQuery()->pluck('id')->all()
        );
    }
}
