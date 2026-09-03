<?php

namespace Tests\Feature\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Enums\CorbeilleEnum;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaiementsDmgWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_roles_dmg_et_cip_n_ont_pas_le_meme_acces(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $dmg = User::factory()->create();
        $dmg->assignRole('dmg');
        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $this->actingAs($dmg)->get('/dmg/paiements')->assertOk();
        $this->actingAs($cip)->get('/dmg/paiements')->assertForbidden();
        $this->actingAs($cip)->post('/dmg/paiements/generer', [])->assertForbidden();
    }

    public function test_la_liste_suit_la_corbeille_du_parcours_et_non_la_nature_importee(): void
    {
        // La classification en cohortes (DmgService::applyCohorteFilter) compare le jour de
        // création du droit de paiement au jour de début du stage : on fige la date courante
        // pour que ce test reste déterministe quel que soit le jour d'exécution de la suite.
        $this->travelTo(Carbon::parse('2026-08-04'), function () {
            $this->seed(RolePermissionSeeder::class);
            $user = User::factory()->create();
            $user->assignRole('administrateur');
            $periode = Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);

            $demarrage = $this->paiement($periode, CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE, 'PRESENCE', '2026-08-03');
            $this->paiement($periode, CorbeilleEnum::CA_VALIDATION_POINTAGES, 'PRESENCE', '2026-08-10');

            // `compteurs` et `attenteDemarrage` sont différés (Inertia::defer) : la première
            // réponse ne porte que le squelette, les données arrivent dans les requêtes
            // partielles suivantes — celles que `loadDeferredProps()` rejoue ici.
            $this->actingAs($user)->get('/dmg/paiements?mois=2026-08')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Dmg/Paiements/Index')
                    ->loadDeferredProps(['compteurs', 'demarrage'], fn (Assert $differe) => $differe
                        ->where('compteurs.global.demarrage', 1)
                        ->where('compteurs.presence', 0)
                        ->has('attenteDemarrage', 1)
                        ->where('attenteDemarrage.0.id', $demarrage->id)));

            $this->get('/dmg/paiements/generer-pdf?type=etat_paiement&mois=2026-08&ids[]='.$demarrage->id)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        });
    }

    public function test_la_presence_ignore_la_cohorte_selectionnee(): void
    {
        // La cohorte ne qualifie que l'entrée en stage. Compter ou filtrer la présence avec
        // elle vidait l'onglet : sur 2026-08 la page annonçait 1 stagiaire (le résidu « hors
        // cohorte ») pour 2 415 attendus par l'ancien Gestage.
        $this->travelTo(Carbon::parse('2026-08-04'), function () {
            $this->seed(RolePermissionSeeder::class);
            $user = User::factory()->create();
            $user->assignRole('administrateur');
            $periode = Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);

            // Stage demarre le 10 du mois precedent, droit cree ce mois-ci : cohorte 2.
            $presence = $this->paiement($periode, CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE, 'PRESENCE', '2026-07-10');

            $this->actingAs($user)->get('/dmg/paiements?mois=2026-08&cohorte=cohorte1')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->loadDeferredProps(['compteurs', 'presence'], fn (Assert $differe) => $differe
                        ->where('compteurs.presence', 1)
                        ->has('attentePresence', 1)
                        ->where('attentePresence.0.id', $presence->id)));

            $this->getJson('/dmg/paiements/json?mois=2026-08&type=presence&cohorte=cohorte1')
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.id', $presence->id);
        });
    }

    public function test_la_validation_selectionnee_cree_un_dossier_et_trace_la_decision(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrateur');
        $periode = Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);
        $paiement = $this->paiement($periode, CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE, 'PRESENCE', '2026-08-10');

        $this->actingAs($user)->post('/dmg/paiements/generer', [
            'periode_id' => $periode->id,
            'paiement_ids' => [$paiement->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('paiements', ['id' => $paiement->id, 'statut' => 'EN_DOSSIER']);
        $this->assertDatabaseHas('dossiers_paiement', ['periode_id' => $periode->id, 'nature' => 'PS', 'statut' => 'BROUILLON']);
        $this->assertDatabaseHas('lignes_dossiers_paiement', ['paiement_id' => $paiement->id, 'retire_le' => null]);
        $this->assertDatabaseHas('decisions_paiements', ['paiement_id' => $paiement->id, 'decision' => 'VALIDE_DMG']);

        $dossier = DossierPaiement::firstOrFail();
        $this->post('/dmg/paiements/transmettre/'.$dossier->id)->assertRedirect();
        $dossier->update(['statut' => 'VALIDE_CB']);
        $this->post('/dmg/paiements/elaborer-op', ['dossiers' => [$dossier->id], 'periode_id' => $periode->id])->assertRedirect()->assertSessionHasNoErrors();
        $op = OrdrePaiement::firstOrFail();
        $this->post('/dmg/paiements/creer-bordereau', ['ops' => [$op->id], 'periode_id' => $periode->id])->assertRedirect()->assertSessionHasNoErrors();
        $bordereau = BordereauPaiement::firstOrFail();
        $this->post('/dmg/paiements/transmettre-bordereau/'.$bordereau->id)->assertRedirect();
        $this->assertDatabaseHas('bordereau_paiements', ['id' => $bordereau->id, 'statut' => 'TRANSMIS_AC']);
    }

    public function test_le_multi_dossier_regroupe_et_transmet_des_dossiers_compatibles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrateur');
        $periode = Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);
        $agence = Agence::factory()->create();
        $source = SourceFinancement::factory()->create();
        $dossiers = collect([45000, 50000])->map(fn (int $montant) => DossierPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'periode_id' => $periode->id,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'numero' => 'PS-TEST-'.Str::random(8),
            'nature' => 'PS',
            'statut' => 'BROUILLON',
            'montant_total' => $montant,
        ]));

        $this->actingAs($user)->post('/dmg/paiements/groupes', [
            'periode_id' => $periode->id,
            'dossiers' => $dossiers->pluck('id')->all(),
            'observation' => 'Regroupement de test',
        ])->assertRedirect();

        $groupe = DossierGroupe::firstOrFail();
        $this->assertMatchesRegularExpression('/^PS08-\d+-G$/', $groupe->numero);
        $this->assertSame('95000.00', $groupe->montant_total);
        $this->assertDatabaseCount('lignes_dossiers_groupes', 2);

        $this->post("/dmg/paiements/groupes/{$groupe->id}/transmettre")->assertRedirect();
        $this->assertDatabaseHas('dossiers_groupes', ['id' => $groupe->id, 'statut' => 'TRANSMIS_CB']);
        $this->assertSame(2, DossierPaiement::whereIn('id', $dossiers->pluck('id'))->where('statut', 'TRANSMIS_CB')->count());
    }

    public function test_le_parefeu_dmg_exclut_les_doublons_cmu_et_type_stage_cmu(): void
    {
        $this->travelTo(Carbon::parse('2026-08-04'), function () {
            $this->seed(RolePermissionSeeder::class);
            $user = User::factory()->create();
            $user->assignRole('administrateur');
            $periode = Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);
            $typeStage = TypeStage::factory()->create();
            $definition = DefinitionParcours::factory()->create();
            $etape = EtapeParcours::factory()->create(['definition_parcours_id' => $definition->id]);
            $numeroCmu = 'CMU-PAREFEU-123';
            $paiementIds = [];

            foreach (range(1, 2) as $index) {
                $beneficiaire = Beneficiaire::factory()->create(['numero_cmu' => $numeroCmu]);
                $stage = Stage::factory()->create([
                    'beneficiaire_id' => $beneficiaire->id,
                    'type_stage_id' => $typeStage->id,
                    'date_debut' => '2026-07-10',
                    'date_fin_prevue' => '2026-09-30',
                ]);
                Contrat::factory()->create(['stage_id' => $stage->id]);
                InstanceParcours::create([
                    'uuid_public' => (string) Str::uuid(),
                    'definition_parcours_id' => $definition->id,
                    'etape_courante_id' => $etape->id,
                    'stage_id' => $stage->id,
                    'corbeille_actuelle' => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value,
                    'version_verrouillage' => 0,
                ]);
                $droit = DroitPaiement::create([
                    'uuid_public' => (string) Str::uuid(),
                    'stage_id' => $stage->id,
                    'periode_id' => $periode->id,
                    'source_financement_id' => $stage->source_financement_id,
                    'nature' => 'PRESENCE',
                    'montant' => 45000,
                    'statut' => 'OUVERT',
                ]);
                $paiementIds[] = Paiement::create([
                    'uuid_public' => (string) Str::uuid(),
                    'droit_paiement_id' => $droit->id,
                    'montant' => 45000,
                    'statut' => 'A_TRAITER',
                ])->id;
            }

            $this->assertSame([], app(DmgService::class)
                ->attentePaiementPresence([], '2026-08')
                ->whereIn('paiements.id', $paiementIds)
                ->pluck('paiements.id')
                ->all());
        });
    }

    public function test_la_reprise_d_un_ajourne_le_replace_dans_sa_corbeille_d_attente(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrateur');
        $periode = Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);

        // Deux origines d'ajournement coexistent : la DMG marque le paiement, le CB renvoie le
        // dossier entier en laissant ses paiements en EN_DOSSIER. L'onglet doit montrer les deux.
        $ajourneDmg = $this->paiement($periode, CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE, 'PRESENCE', '2026-08-10');
        $ajourneCb = $this->paiement($periode, CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE, 'DEMARRAGE', '2026-08-03');

        $this->actingAs($user)->post('/dmg/paiements/ajourner', [
            'paiement_ids' => [$ajourneDmg->id],
            'motif' => 'Piece justificative manquante',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post('/dmg/paiements/generer', ['periode_id' => $periode->id, 'paiement_ids' => [$ajourneCb->id]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $dossier = DossierPaiement::firstOrFail();
        $dossier->update(['statut' => 'AJOURNE_CB']);

        $this->getJson('/dmg/paiements/ajournes?mois=2026-08')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->post('/dmg/paiements/ajournes/reprendre', [
            'paiement_ids' => [$ajourneDmg->id, $ajourneCb->id],
            'motif' => 'Pieces completees par l agence',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('paiements', ['id' => $ajourneDmg->id, 'statut' => 'A_TRAITER']);
        $this->assertDatabaseHas('paiements', ['id' => $ajourneCb->id, 'statut' => 'A_TRAITER']);
        $this->assertDatabaseHas('decisions_paiements', ['paiement_id' => $ajourneDmg->id, 'decision' => 'REPRIS_DMG']);
        // Le paiement repris quitte le dossier ajourné, qui est allégé d'autant.
        $this->assertDatabaseMissing('lignes_dossiers_paiement', ['paiement_id' => $ajourneCb->id, 'retire_le' => null]);
        $this->assertSame('0.00', $dossier->fresh()->montant_total);
        // Chaque paiement retrouve la corbeille correspondant a la nature de son droit.
        $this->assertSame(
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value,
            $ajourneDmg->fresh()->droitPaiement->stage->instanceParcours->corbeille_actuelle,
        );
        $this->assertSame(
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value,
            $ajourneCb->fresh()->droitPaiement->stage->instanceParcours->corbeille_actuelle,
        );

        $this->getJson('/dmg/paiements/ajournes?mois=2026-08')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_l_ordre_de_paiement_porte_son_titre_et_rend_un_dossier_retire(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrateur');
        $periode = Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);
        $paiement = $this->paiement($periode, CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE, 'PRESENCE', '2026-08-10');

        $this->actingAs($user)->post('/dmg/paiements/generer', ['periode_id' => $periode->id, 'paiement_ids' => [$paiement->id]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $dossier = DossierPaiement::firstOrFail();
        $dossier->update(['statut' => 'VALIDE_CB']);

        $this->post('/dmg/paiements/elaborer-op', [
            'dossiers' => [$dossier->id],
            'periode_id' => $periode->id,
            'libelle' => 'OP paiement presence aout',
            'montant_etat_financement' => 50000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $op = OrdrePaiement::firstOrFail();
        $this->assertSame('OP paiement presence aout', $op->libelle);
        $this->assertSame('50000.00', $op->montant_etat_financement);

        $this->getJson('/dmg/paiements/ops?mois=2026-08')
            ->assertOk()
            ->assertJsonPath('0.libelle', 'OP paiement presence aout')
            ->assertJsonPath('0.dossiers_count', 1)
            ->assertJsonPath('0.stagiaires_count', 1);

        $this->getJson("/dmg/paiements/ops/{$op->id}/dossiers")
            ->assertOk()
            ->assertJsonPath('0.id', $dossier->id)
            ->assertJsonPath('0.nombre_stagiaires', 1);

        $this->post("/dmg/paiements/ops/{$op->id}/retirer-dossier", [
            'dossier_id' => $dossier->id,
            'motif' => 'Rattachement errone',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Le dossier redevient elaborable ; l'OP vide est annule et le motif reste tracable.
        $this->assertDatabaseHas('dossiers_paiement', ['id' => $dossier->id, 'statut' => 'VALIDE_CB', 'ordre_paiement_id' => null]);
        $this->assertDatabaseHas('ordre_paiements', ['id' => $op->id, 'statut' => 'ANNULE']);
        $this->assertDatabaseHas('journaux_audit', ['action' => 'retrait_dossier_op', 'modele_id' => $op->id]);
    }

    public function test_le_retrait_d_un_op_libere_le_bordereau_sans_le_vider(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrateur');
        $periode = Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);
        $source = SourceFinancement::factory()->create();
        $agence = Agence::factory()->create();

        $ops = collect([40000, 60000])->map(function (int $montant) use ($periode, $source, $agence) {
            $dossier = DossierPaiement::create([
                'uuid_public' => (string) Str::uuid(),
                'periode_id' => $periode->id,
                'agence_id' => $agence->id,
                'source_financement_id' => $source->id,
                'numero' => 'PS-OP-'.Str::random(8),
                'nature' => 'PS',
                'statut' => 'VALIDE_CB',
                'montant_total' => $montant,
            ]);

            return app(DmgService::class)->elaborerOp([$dossier->id], $periode->id, 'OP '.$montant);
        });

        $this->actingAs($user)->post('/dmg/paiements/creer-bordereau', [
            'ops' => $ops->pluck('id')->all(),
            'periode_id' => $periode->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $bordereau = BordereauPaiement::firstOrFail();
        $this->assertSame('100000.00', $bordereau->montant_total);

        $this->getJson("/dmg/paiements/bordereaux/{$bordereau->id}/ops")->assertOk()->assertJsonCount(2);

        $retire = $ops->first();
        $this->post("/dmg/paiements/bordereaux/{$bordereau->id}/retirer-op", [
            'op_id' => $retire->id,
            'motif' => 'Montant a corriger avant transmission',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // L'OP retire redevient selectionnable, le bordereau reste en brouillon avec le reliquat.
        $this->assertDatabaseHas('ordre_paiements', ['id' => $retire->id, 'statut' => 'BROUILLON', 'bordereau_paiement_id' => null]);
        $this->assertDatabaseHas('bordereau_paiements', ['id' => $bordereau->id, 'statut' => 'BROUILLON']);
        $this->assertSame('60000.00', $bordereau->fresh()->montant_total);
        $this->assertDatabaseHas('journaux_audit', ['action' => 'retrait_op_bordereau', 'modele_id' => $bordereau->id]);

        $this->getJson('/dmg/paiements/ops?mois=2026-08&statut=BROUILLON')->assertOk()->assertJsonCount(1);
    }

    private function paiement(Periode $periode, CorbeilleEnum $corbeille, string $nature, string $dateDebut): Paiement
    {
        $stage = Stage::factory()->create(['date_debut' => $dateDebut]);
        Contrat::factory()->create(['stage_id' => $stage->id]);
        $definition = DefinitionParcours::factory()->create();
        $etape = EtapeParcours::factory()->create(['definition_parcours_id' => $definition->id]);
        InstanceParcours::create([
            'uuid_public' => (string) Str::uuid(),
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => $stage->id,
            'corbeille_actuelle' => $corbeille->value,
            'version_verrouillage' => 0,
        ]);
        $droit = DroitPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $stage->source_financement_id,
            'nature' => $nature,
            'montant' => 45000,
            'statut' => 'OUVERT',
        ]);

        return Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'droit_paiement_id' => $droit->id,
            'montant' => 45000,
            'statut' => 'A_TRAITER',
        ]);
    }
}
