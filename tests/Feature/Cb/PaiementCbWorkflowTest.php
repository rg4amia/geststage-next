<?php

namespace Tests\Feature\Cb;

use App\Models\Internship\Stage;
use App\Models\Payment\DecisionPaiement;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaiementCbWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $cb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->cb = User::factory()->create();
        $this->cb->assignRole('cb');
    }

    public function test_la_page_liste_les_dossiers_transmis_cb_avec_le_compte_des_lignes_actives(): void
    {
        $periode = $this->periode('2026-08');
        $autrePeriode = $this->periode('2026-07');

        $attendu = $this->dossierAvecPaiements($periode, 'TRANSMIS_CB', 1, 1);
        $this->dossierAvecPaiements($periode, 'BROUILLON');
        $this->dossierAvecPaiements($autrePeriode, 'TRANSMIS_CB');
        $ajourne = $this->dossierAvecPaiements($periode, 'AJOURNE_CB');

        $this->actingAs($this->cb)
            ->get('/cb/paiements?mois=2026-08')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cb/Paiements/Index')
                ->has('dossiersControle', 1)
                ->where('dossiersControle.0.id', $attendu['dossier']->id)
                ->where('dossiersControle.0.nombre_stagiaires', 1)
                ->has('etatsAjournes', 1)
                ->where('etatsAjournes.0.id', $ajourne['dossier']->id)
                ->where('moisActuel', '2026-08'));

        $this->getJson('/cb/paiements/dossiers?mois=2026-08')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $attendu['dossier']->id)
            ->assertJsonPath('0.nombre_stagiaires', 1);
    }

    public function test_la_validation_cb_valide_le_dossier_et_trace_chaque_paiement_actif(): void
    {
        $chaine = $this->dossierAvecPaiements($this->periode('2026-08'), 'TRANSMIS_CB', 2);

        $this->actingAs($this->cb)
            ->from('/cb/paiements?mois=2026-08')
            ->post('/cb/paiements/valider/'.$chaine['dossier']->id)
            ->assertRedirect('/cb/paiements?mois=2026-08')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('dossiers_paiement', [
            'id' => $chaine['dossier']->id,
            'statut' => 'VALIDE_CB',
            'ordre_paiement_id' => null,
        ]);

        foreach ($chaine['paiementsActifs'] as $paiement) {
            $this->assertDatabaseHas('paiements', [
                'id' => $paiement->id,
                'statut' => 'EN_DOSSIER',
            ]);
            $this->assertDatabaseHas('decisions_paiements', [
                'paiement_id' => $paiement->id,
                'auteur_id' => $this->cb->id,
                'decision' => 'VALIDATION_DOSSIER_CB',
                'statut_avant' => 'EN_DOSSIER',
                'statut_apres' => 'EN_DOSSIER',
            ]);
        }
    }

    public function test_l_ajournement_cb_exige_un_motif_conserve_les_paiements_et_trace_la_ligne_active(): void
    {
        $chaine = $this->dossierAvecPaiements($this->periode('2026-08'), 'TRANSMIS_CB', 1, 1);
        $motif = 'Piece justificative non conforme';

        $this->actingAs($this->cb)
            ->from('/cb/paiements?mois=2026-08')
            ->post('/cb/paiements/ajourner/'.$chaine['dossier']->id, ['motif' => ''])
            ->assertRedirect('/cb/paiements?mois=2026-08')
            ->assertSessionHasErrors('motif');

        $this->post('/cb/paiements/ajourner/'.$chaine['dossier']->id, ['motif' => $motif])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $paiementActif = $chaine['paiementsActifs']->first();
        $paiementRetire = $chaine['paiementsRetires']->first();

        $this->assertDatabaseHas('dossiers_paiement', [
            'id' => $chaine['dossier']->id,
            'statut' => 'AJOURNE_CB',
        ]);
        $this->assertDatabaseHas('paiements', [
            'id' => $paiementActif->id,
            'statut' => 'EN_DOSSIER',
        ]);
        $this->assertDatabaseHas('lignes_dossiers_paiement', [
            'dossier_paiement_id' => $chaine['dossier']->id,
            'paiement_id' => $paiementActif->id,
            'retire_le' => null,
            'motif_retrait' => $motif,
        ]);
        $this->assertDatabaseMissing('decisions_paiements', [
            'paiement_id' => $paiementRetire->id,
            'decision' => 'AJOURNEMENT_DOSSIER_CB',
        ]);
        $this->assertDatabaseHas('decisions_paiements', [
            'paiement_id' => $paiementActif->id,
            'decision' => 'AJOURNEMENT_DOSSIER_CB',
            'motif' => $motif,
            'statut_avant' => 'EN_DOSSIER',
            'statut_apres' => 'EN_DOSSIER',
        ]);
    }

    public function test_un_dossier_deja_traite_ou_deja_rattache_a_une_op_ne_peut_pas_etre_retraite_cb(): void
    {
        $periode = $this->periode('2026-08');
        $op = OrdrePaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'numero' => 'OP-CB-'.Str::upper(Str::random(8)),
            'periode_id' => $periode->id,
            'source_financement_id' => SourceFinancement::factory()->create()->id,
            'montant_total' => 50000,
            'statut' => 'BROUILLON',
        ]);
        $dejaOp = $this->dossierAvecPaiements($periode, 'TRANSMIS_CB', 1, 0, $op);
        $dejaValide = $this->dossierAvecPaiements($periode, 'VALIDE_CB');

        $this->actingAs($this->cb)
            ->from('/cb/paiements?mois=2026-08')
            ->post('/cb/paiements/valider/'.$dejaOp['dossier']->id)
            ->assertRedirect('/cb/paiements?mois=2026-08')
            ->assertSessionHasErrors('dossier');

        $this->post('/cb/paiements/ajourner/'.$dejaValide['dossier']->id, [
            'motif' => 'Tentative de retraitement',
        ])->assertRedirect('/cb/paiements?mois=2026-08')
            ->assertSessionHasErrors('dossier');

        $this->assertSame(0, DecisionPaiement::count());
    }

    public function test_un_utilisateur_sans_role_cb_est_rejete_sur_les_routes_cb(): void
    {
        $chaine = $this->dossierAvecPaiements($this->periode('2026-08'), 'TRANSMIS_CB', 1);
        $intrus = User::factory()->create();

        $this->actingAs($intrus)->get('/cb/paiements')->assertForbidden();
        $this->actingAs($intrus)->getJson('/cb/paiements/dossiers')->assertForbidden();
        $this->actingAs($intrus)->postJson('/cb/paiements/stagiaires', ['dossier_id' => $chaine['dossier']->id])->assertForbidden();
        $this->actingAs($intrus)->getJson('/cb/paiements/documents?stage_id=1')->assertForbidden();
        $this->actingAs($intrus)->post('/cb/paiements/valider/'.$chaine['dossier']->id)->assertForbidden();
        $this->actingAs($intrus)->post('/cb/paiements/ajourner/'.$chaine['dossier']->id, ['motif' => 'Motif suffisant'])->assertForbidden();
    }

    public function test_la_validation_d_un_multi_dossier_bascule_tous_ses_membres_et_le_groupe(): void
    {
        $periode = $this->periode('2026-08');
        $premier = $this->dossierAvecPaiements($periode, 'TRANSMIS_CB', 1);
        $second = $this->dossierAvecPaiements($periode, 'TRANSMIS_CB', 1);
        $groupe = $this->groupe($periode, [$premier['dossier'], $second['dossier']], 'TRANSMIS_CB');

        $this->actingAs($this->cb)
            ->from('/cb/paiements?mois=2026-08')
            ->post("/cb/paiements/groupes/{$groupe->id}/valider")
            ->assertRedirect('/cb/paiements?mois=2026-08')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('dossiers_groupes', ['id' => $groupe->id, 'statut' => 'VALIDE_CB']);
        $this->assertDatabaseHas('dossiers_paiement', ['id' => $premier['dossier']->id, 'statut' => 'VALIDE_CB']);
        $this->assertDatabaseHas('dossiers_paiement', ['id' => $second['dossier']->id, 'statut' => 'VALIDE_CB']);
    }

    public function test_la_liste_des_dossiers_du_mois_remplace_les_dossiers_groupes_par_leur_multi_dossier(): void
    {
        $periode = $this->periode('2026-08');
        $premier = $this->dossierAvecPaiements($periode, 'TRANSMIS_CB', 1);
        $second = $this->dossierAvecPaiements($periode, 'TRANSMIS_CB', 1);
        $isole = $this->dossierAvecPaiements($periode, 'TRANSMIS_CB', 1);
        $groupe = $this->groupe($periode, [$premier['dossier'], $second['dossier']], 'TRANSMIS_CB');

        $reponse = $this->actingAs($this->cb)
            ->getJson('/cb/paiements/dossiers?mois=2026-08')
            ->assertOk();

        $lignes = collect($reponse->json());

        $this->assertTrue($lignes->contains(fn ($l) => $l['type'] === 'groupe' && $l['id'] === $groupe->id));
        $this->assertTrue($lignes->contains(fn ($l) => $l['type'] === 'dossier' && $l['id'] === $isole['dossier']->id));
        $this->assertFalse($lignes->contains(fn ($l) => $l['type'] === 'dossier' && $l['id'] === $premier['dossier']->id));
        $this->assertFalse($lignes->contains(fn ($l) => $l['type'] === 'dossier' && $l['id'] === $second['dossier']->id));
    }

    public function test_l_ajournement_d_un_multi_dossier_bascule_tous_ses_membres_et_le_groupe(): void
    {
        $periode = $this->periode('2026-08');
        $premier = $this->dossierAvecPaiements($periode, 'TRANSMIS_CB', 1);
        $second = $this->dossierAvecPaiements($periode, 'TRANSMIS_CB', 1);
        $groupe = $this->groupe($periode, [$premier['dossier'], $second['dossier']], 'TRANSMIS_CB');

        $this->actingAs($this->cb)
            ->from('/cb/paiements?mois=2026-08')
            ->post("/cb/paiements/groupes/{$groupe->id}/ajourner", ['motif' => 'Piece manquante sur les deux dossiers'])
            ->assertRedirect('/cb/paiements?mois=2026-08')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('dossiers_groupes', ['id' => $groupe->id, 'statut' => 'AJOURNE_CB']);
        $this->assertDatabaseHas('dossiers_paiement', ['id' => $premier['dossier']->id, 'statut' => 'AJOURNE_CB']);
        $this->assertDatabaseHas('dossiers_paiement', ['id' => $second['dossier']->id, 'statut' => 'AJOURNE_CB']);
    }

    public function test_l_ajournement_partiel_retire_seulement_les_paiements_selectionnes(): void
    {
        $chaine = $this->dossierAvecPaiements($this->periode('2026-08'), 'TRANSMIS_CB', 3);
        $motif = 'Piece justificative manquante pour ce stagiaire';
        $paiementAjourne = $chaine['paiementsActifs']->first();

        $this->actingAs($this->cb)
            ->from('/cb/paiements?mois=2026-08')
            ->post('/cb/paiements/ajourner-stagiaires', [
                'paiement_ids' => [$paiementAjourne->id],
                'motif' => $motif,
            ])
            ->assertRedirect('/cb/paiements?mois=2026-08')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('dossiers_paiement', [
            'id' => $chaine['dossier']->id,
            'statut' => 'TRANSMIS_CB',
        ]);
        $this->assertDatabaseHas('paiements', [
            'id' => $paiementAjourne->id,
            'statut' => 'A_TRAITER',
        ]);
        $this->assertDatabaseHas('lignes_dossiers_paiement', [
            'dossier_paiement_id' => $chaine['dossier']->id,
            'paiement_id' => $paiementAjourne->id,
            'motif_retrait' => $motif,
        ]);
        $this->assertDatabaseMissing('lignes_dossiers_paiement', [
            'dossier_paiement_id' => $chaine['dossier']->id,
            'paiement_id' => $paiementAjourne->id,
            'retire_le' => null,
        ]);
        $this->assertDatabaseHas('decisions_paiements', [
            'paiement_id' => $paiementAjourne->id,
            'decision' => 'AJOURNEMENT_STAGIAIRE_CB',
            'motif' => $motif,
            'statut_avant' => 'EN_DOSSIER',
            'statut_apres' => 'A_TRAITER',
        ]);

        $restants = $chaine['paiementsActifs']->skip(1);
        foreach ($restants as $paiement) {
            $this->assertDatabaseHas('paiements', ['id' => $paiement->id, 'statut' => 'EN_DOSSIER']);
        }
    }

    public function test_l_ajournement_partiel_refuse_de_vider_totalement_le_dossier(): void
    {
        $chaine = $this->dossierAvecPaiements($this->periode('2026-08'), 'TRANSMIS_CB', 2);

        $this->actingAs($this->cb)
            ->from('/cb/paiements?mois=2026-08')
            ->post('/cb/paiements/ajourner-stagiaires', [
                'paiement_ids' => $chaine['paiementsActifs']->pluck('id')->all(),
                'motif' => 'Tentative de vider le dossier entier',
            ])
            ->assertRedirect('/cb/paiements?mois=2026-08')
            ->assertSessionHasErrors('paiement_ids');

        foreach ($chaine['paiementsActifs'] as $paiement) {
            $this->assertDatabaseHas('paiements', ['id' => $paiement->id, 'statut' => 'EN_DOSSIER']);
        }
        $this->assertDatabaseHas('dossiers_paiement', ['id' => $chaine['dossier']->id, 'statut' => 'TRANSMIS_CB']);
    }

    public function test_le_signal_doublon_est_informatif_et_ne_bloque_pas_la_validation(): void
    {
        $chaine = $this->dossierAvecPaiements($this->periode('2026-08'), 'TRANSMIS_CB', 2);
        $numeroPartage = 'CMU-DOUBLON-001';

        foreach ($chaine['paiementsActifs'] as $paiement) {
            DB::table('beneficiaires')
                ->join('stages', 'stages.beneficiaire_id', '=', 'beneficiaires.id')
                ->join('droits_paiement', 'droits_paiement.stage_id', '=', 'stages.id')
                ->where('droits_paiement.id', $paiement->droit_paiement_id)
                ->update(['beneficiaires.numero_cmu' => $numeroPartage]);
        }

        $reponse = $this->actingAs($this->cb)
            ->postJson('/cb/paiements/stagiaires', ['dossier_id' => $chaine['dossier']->id])
            ->assertOk();

        $this->assertSame(2, $reponse->json('doublonCount'));
        foreach ($reponse->json('data') as $ligne) {
            $this->assertTrue($ligne['doublon']);
        }

        $this->actingAs($this->cb)
            ->from('/cb/paiements?mois=2026-08')
            ->post('/cb/paiements/valider/'.$chaine['dossier']->id)
            ->assertRedirect('/cb/paiements?mois=2026-08')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('dossiers_paiement', ['id' => $chaine['dossier']->id, 'statut' => 'VALIDE_CB']);
    }

    private function groupe(Periode $periode, array $dossiers, string $statut): DossierGroupe
    {
        $groupe = DossierGroupe::create([
            'uuid_public' => (string) Str::uuid(),
            'periode_id' => $periode->id,
            'source_financement_id' => $dossiers[0]->source_financement_id,
            'numero' => 'GRP-CB-'.Str::upper(Str::random(8)),
            'nature' => 'PS',
            'statut' => $statut,
            'montant_total' => collect($dossiers)->sum('montant_total'),
        ]);

        foreach ($dossiers as $dossier) {
            DB::table('lignes_dossiers_groupes')->insert([
                'dossier_groupe_id' => $groupe->id,
                'dossier_paiement_id' => $dossier->id,
                'ajoute_le' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $groupe;
    }

    private function periode(string $code): Periode
    {
        return Periode::create([
            'code' => $code,
            'date_debut' => $code.'-01',
            'date_fin' => $code.'-28',
        ]);
    }

    /**
     * @return array{
     *     dossier: DossierPaiement,
     *     paiementsActifs: Collection<int, Paiement>,
     *     paiementsRetires: Collection<int, Paiement>
     * }
     */
    private function dossierAvecPaiements(
        Periode $periode,
        string $statut,
        int $actifs = 1,
        int $retires = 0,
        ?OrdrePaiement $op = null,
    ): array {
        $agence = Agence::factory()->create();
        $source = $op?->sourceFinancement ?? SourceFinancement::factory()->create();
        $dossier = DossierPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'periode_id' => $periode->id,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'ordre_paiement_id' => $op?->id,
            'numero' => 'DOS-CB-'.Str::upper(Str::random(8)),
            'nature' => 'PS',
            'statut' => $statut,
            'montant_total' => ($actifs + $retires) * 50000,
        ]);

        $paiementsActifs = collect();
        for ($index = 0; $index < $actifs; $index++) {
            $paiement = $this->paiement($periode, $agence, $source);
            $dossier->paiements()->attach($paiement->id, ['montant' => 50000, 'ajoute_le' => now()]);
            $paiementsActifs->push($paiement);
        }

        $paiementsRetires = collect();
        for ($index = 0; $index < $retires; $index++) {
            $paiement = $this->paiement($periode, $agence, $source);
            $dossier->paiements()->attach($paiement->id, [
                'montant' => 50000,
                'ajoute_le' => now()->subMinute(),
                'retire_le' => now(),
                'motif_retrait' => 'Retiré avant contrôle CB',
            ]);
            $paiementsRetires->push($paiement);
        }

        return compact('dossier', 'paiementsActifs', 'paiementsRetires');
    }

    private function paiement(Periode $periode, Agence $agence, SourceFinancement $source): Paiement
    {
        $stage = Stage::factory()->create([
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
        ]);
        $droit = DroitPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $source->id,
            'nature' => 'PRESENCE',
            'montant' => 50000,
            'statut' => 'OUVERT',
        ]);

        return Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'droit_paiement_id' => $droit->id,
            'montant' => 50000,
            'statut' => 'EN_DOSSIER',
        ]);
    }
}
