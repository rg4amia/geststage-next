<?php

namespace Tests\Feature\AgentCompt;

use App\Domain\Payment\Services\DmgService;
use App\Enums\CorbeilleEnum;
use App\Models\Internship\Stage;
use App\Models\Payment\BordereauPaiement;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaiementAcWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $agentComptable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->agentComptable = User::factory()->create();
        $this->agentComptable->assignRole('agent_comptable');
    }

    public function test_la_liste_reproduit_le_perimetre_legacy_sans_bordereau_vide_ou_deja_valide(): void
    {
        $periode = $this->periode('2026-08');
        $autrePeriode = $this->periode('2026-07');

        $attente = $this->chainePaiement($periode, 'TRANSMIS_AC', 'EN_OP');
        $this->chainePaiement($periode, 'TRANSMIS_AC', 'VALIDE_AC');
        $vise = $this->chainePaiement($periode, 'VISE_AC', 'VALIDE_AC');
        $vise['ordre']->update(['statut' => 'VISE_AC']);
        $vise['dossier']->update(['statut' => 'VISE_AC']);
        $rejete = $this->chainePaiement($periode, 'REJETE_AC', 'A_TRAITER');
        $this->chainePaiement($autrePeriode, 'TRANSMIS_AC', 'EN_OP');
        $this->bordereauVide($periode, 'TRANSMIS_AC');
        $retire = $this->chainePaiement($periode, 'TRANSMIS_AC', 'EN_OP');
        $retire['dossier']->paiements()->updateExistingPivot($retire['paiement']->id, ['retire_le' => now()]);

        $this->actingAs($this->agentComptable)
            ->get('/agent-comptable/paiements?mois=2026-08')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AgentComptable/Paiements/Index')
                ->has('bordereauxAttente', 1)
                ->where('bordereauxAttente.0.id', $attente['bordereau']->id)
                ->where('bordereauxAttente.0.nombre_ordres', 1)
                ->where('bordereauxAttente.0.nombre_dossiers', 1)
                ->where('bordereauxAttente.0.nombre_paiements', 1)
                ->where('bordereauxAttente.0.ordres.0.id', $attente['ordre']->id)
                ->has('bordereauxRejetes', 1)
                ->where('bordereauxRejetes.0.id', $rejete['bordereau']->id)
                ->has('bordereauxVises', 1)
                ->where('bordereauxVises.0.id', $vise['bordereau']->id)
                ->has('statutPaiements', 1)
                ->where('statutPaiements.0.ordres.0.id', $vise['ordre']->id)
                ->where('moisActuel', '2026-08'));
    }

    public function test_les_vues_legacy_status_validation_et_operation_rejete_sont_integrees_a_lindex(): void
    {
        $periode = $this->periode('2026-08');

        $statusPaiement = $this->chainePaiement($periode, 'TRANSMIS_AC', 'PAYE', null);
        $statusPaiement['ordre']->update(['statut' => 'VISE_AC']);
        $statusPaiement['dossier']->update(['statut' => 'VISE_AC']);
        $this->ajouterPaiement($statusPaiement, 'VALIDE_AC', null);

        $operationRejetee = $this->chainePaiement($periode, 'TRANSMIS_AC', 'REJETE_AC', CorbeilleEnum::DMG_OP_REJETE_AC);
        $operationRejetee['ordre']->update(['statut' => 'REJETE_AC']);
        $operationRejetee['dossier']->update(['statut' => 'REJETE_AC']);
        $operationRejetee['dossier']->paiements()->updateExistingPivot($operationRejetee['paiement']->id, [
            'motif_retrait' => 'Compte bénéficiaire incorrect',
        ]);

        $this->actingAs($this->agentComptable)
            ->get('/agent-comptable/paiements?mois=2026-08')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AgentComptable/Paiements/Index')
                ->has('statutPaiements', 1)
                ->where('statutPaiements.0.id', $statusPaiement['bordereau']->id)
                ->where('statutPaiements.0.ordres.0.id', $statusPaiement['ordre']->id)
                ->where('statutPaiements.0.ordres.0.compteurs.total', 2)
                ->where('statutPaiements.0.ordres.0.compteurs.payes', 1)
                ->where('statutPaiements.0.ordres.0.compteurs.nonPayes', 0)
                ->where('statutPaiements.0.ordres.0.compteurs.valides', 1)
                ->has('operationsRejetees', 1)
                ->where('operationsRejetees.0.id', $operationRejetee['bordereau']->id)
                ->where('operationsRejetees.0.ordres.0.id', $operationRejetee['ordre']->id)
                ->where('operationsRejetees.0.motif', 'Compte bénéficiaire incorrect')
                ->has('bordereauxRejetes', 0));

        $this->get('/agent-comptable/status-validation?mois=2026-08')
            ->assertRedirect('/agent-comptable/paiements?mois=2026-08&vue=statuts');

        $this->get('/agent-comptable/operation-rejete?mois=2026-08')
            ->assertRedirect('/agent-comptable/paiements?mois=2026-08&vue=operations_rejetees');
    }

    public function test_les_onglets_globaux_payes_et_non_payes_listent_les_paiements_tranches_du_mois(): void
    {
        $periode = $this->periode('2026-08');
        $autrePeriode = $this->periode('2026-07');

        $paye = $this->chainePaiement($periode, 'VISE_AC', 'PAYE', null);
        $paye['ordre']->update(['statut' => 'VISE_AC']);
        $paye['paiement']->update(['paye_le' => now()->subDay()]);

        $nonPaye = $this->chainePaiement($periode, 'VISE_AC', 'NON_PAYE', null);
        $nonPaye['ordre']->update(['statut' => 'VISE_AC']);

        // Un paiement payé sur une autre période ne doit pas polluer le mois demandé.
        $horsMois = $this->chainePaiement($autrePeriode, 'VISE_AC', 'PAYE', null);

        $this->actingAs($this->agentComptable)
            ->get('/agent-comptable/paiements?mois=2026-08')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AgentComptable/Paiements/Index')
                ->where('statutCompteurs.payes', 1)
                ->where('statutCompteurs.nonPayes', 1)
                ->where('sousOngletActuel', 'par_op'));

        $this->getJson('/agent-comptable/paiements/statuts?mois=2026-08&situation=paye')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('montant_total', 50000)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.stagiaire.nom', $paye['paiement']->droitPaiement->stage->beneficiaire->nom)
            ->assertJsonPath('rows.0.dossier.numero', $paye['dossier']->numero)
            ->assertJsonPath('rows.0.ordre.numero', $paye['ordre']->numero)
            ->assertJsonPath('rows.0.stagiaire.statut_paiement', 'PAYE')
            ->assertJsonPath('rows.0.stagiaire.paye_le', $paye['paiement']->paye_le->format('d/m/Y H:i'));

        $this->getJson('/agent-comptable/paiements/statuts?mois=2026-08&situation=non_paye')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.stagiaire.statut_paiement', 'NON_PAYE');

        $this->getJson('/agent-comptable/paiements/statuts?mois=2026-08&situation=paye&page=2')
            ->assertOk()
            ->assertJsonCount(0, 'rows');

        $this->getJson('/agent-comptable/paiements/statuts?mois=2026-08&situation=annule')
            ->assertStatus(422);
    }

    public function test_les_onglets_globaux_payes_sont_pagines_par_lots_de_cent(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'VISE_AC', 'PAYE', null);
        $chaine['ordre']->update(['statut' => 'VISE_AC']);

        for ($i = 0; $i < 99; $i++) {
            $this->ajouterPaiement($chaine, 'PAYE', null);
        }

        $this->actingAs($this->agentComptable)
            ->getJson('/agent-comptable/paiements/statuts?mois=2026-08&situation=paye')
            ->assertOk()
            ->assertJsonPath('total', 100)
            ->assertJsonCount(100, 'rows')
            ->assertJsonPath('per_page', 100);

        $this->getJson('/agent-comptable/paiements/statuts?mois=2026-08&situation=paye&page=2')
            ->assertOk()
            ->assertJsonCount(0, 'rows')
            ->assertJsonPath('total', 100);
    }

    public function test_lexport_des_onglets_globaux_produit_un_excel_et_un_pdf(): void
    {
        $this->periode('2026-07');
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'VISE_AC', 'PAYE', null);
        $chaine['ordre']->update(['statut' => 'VISE_AC']);
        $chaine['paiement']->update(['paye_le' => now()]);

        $this->actingAs($this->agentComptable);

        $excel = $this->get('/agent-comptable/paiements/statuts/export?mois=2026-08&situation=paye&format=excel');
        $excel->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $excel->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('paiements-payes-2026-08.xlsx', $excel->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $excel->streamedContent());

        $pdf = $this->get('/agent-comptable/paiements/statuts/export?mois=2026-08&situation=paye&format=pdf');
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('paiements-payes-2026-08.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->getJson('/agent-comptable/paiements/statuts/export?mois=2026-08&situation=paye&format=csv')
            ->assertStatus(422);

        $this->get('/agent-comptable/paiements/statuts/export?mois=2026-07&situation=paye&format=excel')
            ->assertNotFound();
    }

    public function test_les_pieces_jointes_legacy_ne_sont_exposees_que_si_le_fichier_existe(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $stage = $chaine['paiement']->droitPaiement->stage;

        $executionId = DB::table('executions_migration')->insertGetId([
            'uuid_public' => (string) Str::uuid(),
            'version_source' => 'gestage-mysql-v2',
            'statut' => 'TERMINEE',
        ]);
        DB::table('conservations_contrats_pae')->insert([
            'execution_migration_id' => $executionId,
            'contrat_pae_ancien_id' => 90001,
            'stage_id' => $stage->id,
            'nombre_colonnes_source' => 6,
            'donnees_originales' => json_encode([
                'file_cni' => 'PIECE_IDENTITE/cni.pdf',
                'file_fiche_yup' => 'FICHE_YUP/absente.pdf',
                'file_contrat' => '0',
                'filecontratavenant' => '0',
                'file_attestation' => null,
                'attest_presence' => '0',
            ]),
            'empreinte_originale' => str_repeat('a', 64),
            'version_schema_source' => 'gestage-mysql-v2',
        ]);

        $racine = sys_get_temp_dir().'/ac-pieces-'.Str::random(8);
        mkdir($racine.'/PIECE_IDENTITE', 0777, true);
        file_put_contents($racine.'/PIECE_IDENTITE/cni.pdf', 'CONTENU-CNI');
        config(['filesystems.disks.legacy_pieces.root' => $racine]);

        try {
            $urlCni = route('ac.paiements.piece', ['stage_id' => $stage->id, 'type' => 'cni']);

            $this->actingAs($this->agentComptable)
                ->getJson('/agent-comptable/paiements/pieces?stage_id='.$stage->id)
                ->assertOk()
                ->assertJsonPath('pieces.cni', $urlCni)
                ->assertJsonPath('pieces.tresor', null)
                ->assertJsonPath('pieces.contrat', null)
                ->assertJsonPath('pieces.attestation', null);

            // Le fichier existant est streamé ; un chemin conservé sans fichier répond 404.
            $reponseFichier = $this->get($urlCni);
            $reponseFichier->assertOk();
            $this->assertSame('CONTENU-CNI', $reponseFichier->streamedContent());
            $this->get('/agent-comptable/paiements/piece?stage_id='.$stage->id.'&type=tresor')
                ->assertNotFound();
        } finally {
            File::deleteDirectory($racine);
        }
    }

    public function test_la_transmission_dmg_alimente_la_corbeille_ac_pour_tous_les_paiements(): void
    {
        $chaine = $this->chainePaiement(
            $this->periode('2026-08'),
            'BROUILLON',
            'EN_OP',
            CorbeilleEnum::DMG_OP_ATTENTE_BORDEREAU,
        );

        app(DmgService::class)->transmettreBordereauAc($chaine['bordereau']);

        $this->assertDatabaseHas('bordereau_paiements', [
            'id' => $chaine['bordereau']->id,
            'statut' => 'TRANSMIS_AC',
        ]);
        $this->assertDatabaseHas('paiements', [
            'id' => $chaine['paiement']->id,
            'corbeille_actuelle' => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE->value,
        ]);
    }

    public function test_le_visa_cascade_sur_toute_la_chaine_et_vide_la_corbeille(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $paiementRetire = $this->ajouterPaiementRetire($chaine);

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/viser/'.$chaine['bordereau']->id)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertChaine($chaine, 'VISE_AC', 'VISE_AC', 'VISE_AC', 'VALIDE_AC', null);
        $this->assertDatabaseHas('paiements', [
            'id' => $paiementRetire->id,
            'statut' => 'EN_OP',
            'corbeille_actuelle' => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE->value,
        ]);
    }

    public function test_le_differe_retourne_toute_la_chaine_a_la_dmg_et_conserve_le_motif(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $motif = 'RIB illisible à corriger';

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/ajourner/'.$chaine['bordereau']->id, ['motif' => $motif])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertChaine($chaine, 'REJETE_AC', 'REJETE_AC', 'AJOURNE_DMG', 'A_TRAITER', CorbeilleEnum::DMG_OP_DIFFERE_AC->value);
        $this->assertDatabaseHas('lignes_dossiers_paiement', [
            'dossier_paiement_id' => $chaine['dossier']->id,
            'paiement_id' => $chaine['paiement']->id,
            'motif_retrait' => $motif,
        ]);
    }

    public function test_le_rejet_definitif_clot_toute_la_chaine_et_conserve_le_motif(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $motif = 'Paiement non conforme définitivement';

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/rejeter/'.$chaine['bordereau']->id, ['motif' => $motif])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertChaine($chaine, 'REJETE_AC_DEFINITIF', 'REJETE_AC_DEFINITIF', 'REJETE_AC_DEFINITIF', 'REJETE_DEFINITIF', CorbeilleEnum::DMG_OP_REJETE_AC->value);
        $this->assertDatabaseHas('lignes_dossiers_paiement', ['paiement_id' => $chaine['paiement']->id, 'motif_retrait' => $motif]);
    }

    public function test_un_bordereau_deja_traite_ne_peut_pas_etre_traite_une_seconde_fois(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'VISE_AC', 'VALIDE_AC', null);

        $this->actingAs($this->agentComptable)
            ->from('/agent-comptable/paiements?mois=2026-08')
            ->post('/agent-comptable/paiements/viser/'.$chaine['bordereau']->id)
            ->assertRedirect('/agent-comptable/paiements?mois=2026-08')
            ->assertSessionHasErrors('bordereau');

        $this->assertChaine($chaine, 'VISE_AC', 'EN_BORDEREAU', 'TRANSMIS_AC', 'VALIDE_AC', null);
    }

    public function test_le_detail_dune_op_liste_ses_dossiers_et_ses_stagiaires_actifs(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $this->ajouterPaiementRetire($chaine);
        $beneficiaire = $chaine['paiement']->droitPaiement->stage->beneficiaire;

        $this->actingAs($this->agentComptable)
            ->getJson('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/details')
            ->assertOk()
            ->assertJsonPath('id', $chaine['ordre']->id)
            ->assertJsonPath('dossiers.0.id', $chaine['dossier']->id)
            ->assertJsonCount(1, 'dossiers.0.stagiaires')
            ->assertJsonPath('dossiers.0.stagiaires.0.beneficiaire_id', $beneficiaire->id)
            ->assertJsonPath('dossiers.0.stagiaires.0.numero_aej', $beneficiaire->numero_aej);
    }

    public function test_les_op_sont_validees_progressivement_avant_la_cloture_du_bordereau(): void
    {
        $periode = $this->periode('2026-08');
        $premiere = $this->chainePaiement($periode, 'TRANSMIS_AC', 'EN_OP');
        $seconde = $this->chainePaiement($periode, 'TRANSMIS_AC', 'EN_OP');
        $seconde['ordre']->update(['bordereau_paiement_id' => $premiere['bordereau']->id]);
        $seconde['bordereau']->delete();
        $premiere['bordereau']->update(['montant_total' => 100000]);

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/ordres/'.$premiere['ordre']->id.'/valider')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ordre_paiements', ['id' => $premiere['ordre']->id, 'statut' => 'VISE_AC']);
        $this->assertDatabaseHas('bordereau_paiements', ['id' => $premiere['bordereau']->id, 'statut' => 'TRANSMIS_AC']);

        $this->post('/agent-comptable/paiements/ordres/'.$seconde['ordre']->id.'/valider')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ordre_paiements', ['id' => $seconde['ordre']->id, 'statut' => 'VISE_AC']);
        $this->assertDatabaseHas('bordereau_paiements', ['id' => $premiere['bordereau']->id, 'statut' => 'VISE_AC']);
        $this->assertDatabaseCount('decisions_paiements', 2);
    }

    public function test_differer_une_op_retourne_tous_ses_paiements_a_la_dmg(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/differer', ['motif' => 'Pièces à corriger'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertChaine($chaine, 'REJETE_AC', 'DIFFERE_AC', 'AJOURNE_DMG', 'A_TRAITER', CorbeilleEnum::DMG_OP_DIFFERE_AC->value);
        $this->assertDatabaseHas('decisions_paiements', ['paiement_id' => $chaine['paiement']->id, 'decision' => 'DIFFERE_OP_AC', 'motif' => 'Pièces à corriger']);
    }

    public function test_rejeter_une_op_trace_la_decision_et_clot_le_bordereau_avec_retour(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/rejeter', ['motif' => 'Paiement non conforme'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertChaine($chaine, 'REJETE_AC', 'REJETE_AC', 'REJETE_AC', 'REJETE_AC', CorbeilleEnum::DMG_OP_REJETE_AC->value);
        $this->assertDatabaseHas('decisions_paiements', ['paiement_id' => $chaine['paiement']->id, 'decision' => 'REJET_OP_AC']);
    }

    public function test_retirer_une_op_la_remet_a_disposition_de_la_dmg_sans_supprimer_les_donnees(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/retirer', ['motif' => 'OP à reconstruire'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bordereau_paiements', ['id' => $chaine['bordereau']->id, 'statut' => 'ANNULE', 'montant_total' => 0]);
        $this->assertDatabaseHas('ordre_paiements', ['id' => $chaine['ordre']->id, 'statut' => 'BROUILLON', 'bordereau_paiement_id' => null]);
        $this->assertDatabaseHas('dossiers_paiement', ['id' => $chaine['dossier']->id, 'statut' => 'VALIDE_CB']);
        $this->assertDatabaseHas('paiements', ['id' => $chaine['paiement']->id, 'statut' => 'EN_OP', 'corbeille_actuelle' => CorbeilleEnum::DMG_OP_ATTENTE_BORDEREAU->value]);
        $this->assertDatabaseHas('decisions_paiements', ['paiement_id' => $chaine['paiement']->id, 'decision' => 'RETRAIT_OP_BORDEREAU_AC']);
    }

    public function test_le_detail_dune_op_repartit_les_stagiaires_dans_les_quatre_onglets(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $valide = $this->ajouterPaiement($chaine, 'VALIDE_AC', null);
        $rejete = $this->ajouterPaiement($chaine, 'REJETE_AC', CorbeilleEnum::DMG_OP_REJETE_AC);
        $differe = $this->ajouterPaiement($chaine, 'A_TRAITER', CorbeilleEnum::DMG_OP_DIFFERE_AC);

        $this->actingAs($this->agentComptable);
        $url = '/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/details';

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('onglet', 'attente')
            ->assertJsonPath('compteurs', ['attente' => 1, 'valide' => 1, 'paye' => 0, 'non_paye' => 0, 'rejete' => 1, 'differe' => 1])
            ->assertJsonCount(1, 'dossiers.0.stagiaires')
            ->assertJsonPath('dossiers.0.stagiaires.0.paiement_id', $chaine['paiement']->id);

        foreach (['valide' => $valide, 'rejete' => $rejete, 'differe' => $differe] as $onglet => $paiement) {
            $this->getJson($url.'?onglet='.$onglet)
                ->assertOk()
                ->assertJsonPath('onglet', $onglet)
                ->assertJsonCount(1, 'dossiers.0.stagiaires')
                ->assertJsonPath('dossiers.0.stagiaires.0.paiement_id', $paiement->id);
        }

        // Un onglet inconnu retombe sur la corbeille par défaut.
        $this->getJson($url.'?onglet=inconnu')->assertOk()->assertJsonPath('onglet', 'attente');
    }

    public function test_les_filtres_de_la_barre_de_recherche_restreignent_la_liste_des_stagiaires(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $beneficiaire = $chaine['paiement']->droitPaiement->stage->beneficiaire;
        $stage = $chaine['paiement']->droitPaiement->stage;

        $this->actingAs($this->agentComptable);
        $url = '/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/details';

        $this->getJson($url.'?agence_id='.$chaine['dossier']->agence_id)
            ->assertOk()
            ->assertJsonCount(1, 'dossiers');

        $this->getJson($url.'?agence_id='.($chaine['dossier']->agence_id + 1000))
            ->assertOk()
            ->assertJsonCount(0, 'dossiers')
            // Les compteurs restent ceux de l'OP entière, comme les onglets du legacy.
            ->assertJsonPath('compteurs.attente', 1);

        $this->getJson($url.'?type_stage_id='.$stage->type_stage_id)->assertOk()->assertJsonCount(1, 'dossiers');
        $this->getJson($url.'?entreprise_id='.$stage->entreprise_id)->assertOk()->assertJsonCount(1, 'dossiers');
        $this->getJson($url.'?recherche='.urlencode($beneficiaire->nom))->assertOk()->assertJsonCount(1, 'dossiers');
        $this->getJson($url.'?recherche=introuvable-xyz')->assertOk()->assertJsonCount(0, 'dossiers');
    }

    public function test_differer_des_stagiaires_selectionnes_laisse_lop_ouverte_pour_les_autres(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $second = $this->ajouterPaiement($chaine, 'EN_OP', CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE);
        $motif = 'Compte Trésor Money à régulariser';

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/differer-stagiaires', [
                'motif' => $motif,
                'paiement_ids' => [$second->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('paiements', [
            'id' => $second->id,
            'statut' => 'A_TRAITER',
            'corbeille_actuelle' => CorbeilleEnum::DMG_OP_DIFFERE_AC->value,
        ]);
        $this->assertDatabaseHas('lignes_dossiers_paiement', ['paiement_id' => $second->id, 'motif_retrait' => $motif]);
        $this->assertDatabaseHas('decisions_paiements', ['paiement_id' => $second->id, 'decision' => 'DIFFERE_STAGIAIRE_AC']);

        // Le stagiaire non sélectionné et l'OP restent en attente de visa.
        $this->assertDatabaseHas('paiements', ['id' => $chaine['paiement']->id, 'statut' => 'EN_OP']);
        $this->assertDatabaseHas('ordre_paiements', ['id' => $chaine['ordre']->id, 'statut' => 'EN_BORDEREAU']);
        $this->assertDatabaseHas('bordereau_paiements', ['id' => $chaine['bordereau']->id, 'statut' => 'TRANSMIS_AC']);
    }

    public function test_la_validation_dune_op_epargne_les_stagiaires_deja_differes(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $differe = $this->ajouterPaiement($chaine, 'EN_OP', CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE);

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/differer-stagiaires', [
                'motif' => 'Pièces manquantes au dossier',
                'paiement_ids' => [$differe->id],
            ])
            ->assertSessionHasNoErrors();

        $this->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/valider')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('paiements', ['id' => $chaine['paiement']->id, 'statut' => 'VALIDE_AC']);
        $this->assertDatabaseHas('paiements', [
            'id' => $differe->id,
            'statut' => 'A_TRAITER',
            'corbeille_actuelle' => CorbeilleEnum::DMG_OP_DIFFERE_AC->value,
        ]);
        $this->assertDatabaseHas('ordre_paiements', ['id' => $chaine['ordre']->id, 'statut' => 'VISE_AC']);
    }

    public function test_differer_tous_les_stagiaires_clot_lop_et_le_bordereau(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/differer-stagiaires', [
                'motif' => 'Dossier entièrement à reprendre',
                'paiement_ids' => [$chaine['paiement']->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ordre_paiements', ['id' => $chaine['ordre']->id, 'statut' => 'DIFFERE_AC']);
        $this->assertDatabaseHas('bordereau_paiements', ['id' => $chaine['bordereau']->id, 'statut' => 'REJETE_AC']);
    }

    public function test_un_stagiaire_deja_tranche_ne_peut_pas_etre_differe_une_seconde_fois(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $valide = $this->ajouterPaiement($chaine, 'VALIDE_AC', null);

        $this->actingAs($this->agentComptable)
            ->from('/agent-comptable/paiements')
            ->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/differer-stagiaires', [
                'motif' => 'Tentative de double décision',
                'paiement_ids' => [$valide->id],
            ])
            ->assertSessionHasErrors('paiement_ids');

        $this->assertDatabaseHas('paiements', ['id' => $valide->id, 'statut' => 'VALIDE_AC']);
    }

    public function test_les_actions_disponibles_suivent_les_decisions_deja_prises_sur_lop(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'TRANSMIS_AC', 'EN_OP');
        $this->actingAs($this->agentComptable);
        $url = '/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/details';

        $this->getJson($url)
            ->assertJsonPath('actions', [
                'valider' => true, 'differer' => true, 'differer_stagiaires' => true,
                'rejeter' => true, 'retirer' => true, 'confirmer_paiements' => false,
            ]);

        // Un différé partiel ferme le rejet global et le retrait, comme `checkerAcAction`.
        $this->ajouterPaiement($chaine, 'A_TRAITER', CorbeilleEnum::DMG_OP_DIFFERE_AC);

        $this->getJson($url)
            ->assertJsonPath('actions.rejeter', false)
            ->assertJsonPath('actions.retirer', false)
            ->assertJsonPath('actions.valider', true);
    }

    public function test_lac_confirme_la_situation_des_paiements_apres_le_visa_de_lop(): void
    {
        $chaine = $this->chainePaiement($this->periode('2026-08'), 'VISE_AC', 'VALIDE_AC', null);
        $chaine['ordre']->update(['statut' => 'VISE_AC']);
        $chaine['dossier']->update(['statut' => 'VISE_AC']);
        $second = $this->ajouterPaiement($chaine, 'VALIDE_AC', null);

        $this->actingAs($this->agentComptable)
            ->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/situation-paiements', [
                'paiement_ids' => [$chaine['paiement']->id],
                'situation' => 'PAYE',
                'motif' => 'Paiement Trésor Money confirmé.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('paiements', ['id' => $chaine['paiement']->id, 'statut' => 'PAYE']);
        $this->assertDatabaseHas('decisions_paiements', [
            'paiement_id' => $chaine['paiement']->id,
            'decision' => 'CONFIRMATION_PAIEMENT_AC',
            'motif' => 'Paiement Trésor Money confirmé.',
        ]);

        $this->post('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/situation-paiements', [
            'paiement_ids' => [$second->id],
            'situation' => 'NON_PAYE',
            'motif' => 'Paiement annulé après contrôle.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('paiements', ['id' => $second->id, 'statut' => 'NON_PAYE', 'paye_le' => null]);
        $this->assertDatabaseHas('decisions_paiements', [
            'paiement_id' => $second->id,
            'decision' => 'CONFIRMATION_NON_PAIEMENT_AC',
        ]);

        $this->getJson('/agent-comptable/paiements/ordres/'.$chaine['ordre']->id.'/details?onglet=paye')
            ->assertOk()
            ->assertJsonPath('compteurs.paye', 1)
            ->assertJsonCount(1, 'dossiers.0.stagiaires');
    }

    private function periode(string $code): Periode
    {
        return Periode::create([
            'code' => $code,
            'date_debut' => $code.'-01',
            'date_fin' => $code.'-28',
        ]);
    }

    /** @return array{bordereau: BordereauPaiement, ordre: OrdrePaiement, dossier: DossierPaiement, paiement: Paiement} */
    private function chainePaiement(Periode $periode, string $statutBordereau, string $statutPaiement, ?CorbeilleEnum $corbeille = CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE): array
    {
        $source = SourceFinancement::factory()->create();
        $agence = Agence::factory()->create();
        $stage = Stage::factory()->create(['agence_id' => $agence->id, 'source_financement_id' => $source->id]);
        $suffixe = strtoupper(Str::random(8));

        $droit = DroitPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $source->id,
            'nature' => 'PRESENCE',
            'montant' => 50000,
            'statut' => 'OUVERT',
        ]);
        $paiement = Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'droit_paiement_id' => $droit->id,
            'montant' => 50000,
            'statut' => $statutPaiement,
            'corbeille_actuelle' => $corbeille?->value,
        ]);
        $bordereau = BordereauPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'numero' => 'BORD-'.$suffixe,
            'periode_id' => $periode->id,
            'source_financement_id' => $source->id,
            'montant_total' => 50000,
            'statut' => $statutBordereau,
        ]);
        $ordre = OrdrePaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'numero' => 'OP-'.$suffixe,
            'periode_id' => $periode->id,
            'source_financement_id' => $source->id,
            'bordereau_paiement_id' => $bordereau->id,
            'montant_total' => 50000,
            'statut' => 'EN_BORDEREAU',
        ]);
        $dossier = DossierPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'periode_id' => $periode->id,
            'agence_id' => $agence->id,
            'source_financement_id' => $source->id,
            'numero' => 'DOS-'.$suffixe,
            'nature' => 'PRESENCE',
            'statut' => 'TRANSMIS_AC',
            'montant_total' => 50000,
            'ordre_paiement_id' => $ordre->id,
        ]);
        $dossier->paiements()->attach($paiement->id, ['montant' => 50000, 'ajoute_le' => now()]);

        return compact('bordereau', 'ordre', 'dossier', 'paiement');
    }

    private function bordereauVide(Periode $periode, string $statut): BordereauPaiement
    {
        return BordereauPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'numero' => 'BORD-VIDE-'.strtoupper(Str::random(6)),
            'periode_id' => $periode->id,
            'montant_total' => 0,
            'statut' => $statut,
        ]);
    }

    /** @param array{dossier: DossierPaiement, paiement: Paiement} $chaine */
    private function ajouterPaiement(array $chaine, string $statut, ?CorbeilleEnum $corbeille): Paiement
    {
        $paiement = Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'droit_paiement_id' => $chaine['paiement']->droit_paiement_id,
            'montant' => 25000,
            'statut' => $statut,
            'corbeille_actuelle' => $corbeille?->value,
        ]);
        $chaine['dossier']->paiements()->attach($paiement->id, ['montant' => 25000, 'ajoute_le' => now()]);

        return $paiement;
    }

    /** @param array{dossier: DossierPaiement, paiement: Paiement} $chaine */
    private function ajouterPaiementRetire(array $chaine): Paiement
    {
        $paiement = Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'droit_paiement_id' => $chaine['paiement']->droit_paiement_id,
            'montant' => 10000,
            'statut' => 'EN_OP',
            'corbeille_actuelle' => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE->value,
        ]);
        $chaine['dossier']->paiements()->attach($paiement->id, [
            'montant' => 10000,
            'ajoute_le' => now()->subMinute(),
            'retire_le' => now(),
            'motif_retrait' => 'Retiré avant transmission',
        ]);

        return $paiement;
    }

    /** @param array{bordereau: BordereauPaiement, ordre: OrdrePaiement, dossier: DossierPaiement, paiement: Paiement} $chaine */
    private function assertChaine(array $chaine, string $bordereau, string $ordre, string $dossier, string $paiement, ?string $corbeille): void
    {
        $this->assertDatabaseHas('bordereau_paiements', ['id' => $chaine['bordereau']->id, 'statut' => $bordereau]);
        $this->assertDatabaseHas('ordre_paiements', ['id' => $chaine['ordre']->id, 'statut' => $ordre]);
        $this->assertDatabaseHas('dossiers_paiement', ['id' => $chaine['dossier']->id, 'statut' => $dossier]);
        $this->assertDatabaseHas('paiements', ['id' => $chaine['paiement']->id, 'statut' => $paiement, 'corbeille_actuelle' => $corbeille]);
    }
}
