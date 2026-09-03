<?php

namespace Tests\Feature\Dmg;

use App\Enums\CorbeilleEnum;
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
