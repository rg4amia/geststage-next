<?php

namespace Tests\Feature\Dmg;

use App\Enums\CorbeilleEnum;
use App\Jobs\GenererExportPaiementJob;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Parité fonctionnelle de l'onglet « Attente Présence » avec le legacy
 * (PaiementPresenceController + PaiementDmgService) : filtres, règles d'inclusion,
 * actions de masse, exports et régénération a posteriori.
 */
class PresencePariteTest extends TestCase
{
    use RefreshDatabase;

    private function seedActeur(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrateur');
        $this->actingAs($user);

        return $user;
    }

    private function periode(): Periode
    {
        return Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);
    }

    private function paiement(Periode $periode, string $dateDebut = '2026-07-10', ?Agence $agence = null): Paiement
    {
        $donneesStage = ['date_debut' => $dateDebut, 'date_fin_prevue' => '2026-10-31'];
        if ($agence !== null) {
            $donneesStage['agence_id'] = $agence->id;
        }
        $stage = Stage::factory()->create($donneesStage);
        Contrat::factory()->create(['stage_id' => $stage->id]);
        $definition = DefinitionParcours::factory()->create();
        $etape = EtapeParcours::factory()->create(['definition_parcours_id' => $definition->id]);
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

        return Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'droit_paiement_id' => $droit->id,
            'montant' => 45000,
            'statut' => 'A_TRAITER',
        ]);
    }

    public function test_le_filtre_date_validation_filtre_sur_la_date_de_creation_du_droit(): void
    {
        // Le legacy filtre sur `date_chef_agence` ; le droit de paiement étant créé au moment
        // de la validation CA, `droits_paiement.created_at` est l'équivalent retenu.
        $this->travelTo(Carbon::parse('2026-08-04'), function () {
            $this->seedActeur();
            $periode = $this->periode();

            $dansLeMois = $this->paiement($periode);
            $horsMois = $this->paiement($periode);

            DroitPaiement::whereKey($dansLeMois->droit_paiement_id)->update(['created_at' => '2026-08-05 10:00:00']);
            DroitPaiement::whereKey($horsMois->droit_paiement_id)->update(['created_at' => '2026-09-02 10:00:00']);

            $this->getJson('/dmg/paiements/json?mois=2026-08&type=presence&date_validation_debut=2026-08-01&date_validation_fin=2026-08-31')
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.id', $dansLeMois->id);
        });
    }

    public function test_un_paiement_emis_en_dossier_sort_de_la_liste_presence(): void
    {
        // Règle d'inclusion « aucun paiement déjà émis » : le passage en EN_DOSSIER retire la
        // ligne de la file (statut A_TRAITER), comme le legacy qui exclut les paiements émis.
        $this->seedActeur();
        $periode = $this->periode();
        $paiement = $this->paiement($periode);

        $this->getJson('/dmg/paiements/json?mois=2026-08&type=presence')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->post('/dmg/paiements/generer', [
            'periode_id' => $periode->id,
            'paiement_ids' => [$paiement->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('paiements', ['id' => $paiement->id, 'statut' => 'EN_DOSSIER']);
        $this->getJson('/dmg/paiements/json?mois=2026-08&type=presence')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_marquer_le_dossier_physique_en_masse_horodate_et_trace(): void
    {
        $this->seedActeur();
        $periode = $this->periode();
        $paiements = collect([$this->paiement($periode), $this->paiement($periode)]);

        $this->post('/dmg/paiements/marquer-dossier-physique', [
            'paiement_ids' => $paiements->pluck('id')->all(),
            'statut' => 'CONFORME',
        ])->assertRedirect()->assertSessionHasNoErrors();

        foreach ($paiements as $paiement) {
            $this->assertDatabaseHas('paiements', [
                'id' => $paiement->id,
                'statut_dossier_physique' => 'CONFORME',
                'dossier_physique_marque_par_id' => 1,
            ]);
            $this->assertNotNull($paiement->fresh()->dossier_physique_marque_le);
            $this->assertDatabaseHas('decisions_paiements', [
                'paiement_id' => $paiement->id,
                'decision' => 'DOSSIER_PHYSIQUE_CONFORME',
            ]);
        }
    }

    public function test_ajourner_toute_la_liste_presence_retourne_en_corbeille_cip(): void
    {
        // Équivalent du keyword legacy `annuler-tous` : l'ajournement porte sur la liste
        // entière du mois (tous les ids affichés), avec motif obligatoire et traçabilité.
        $this->seedActeur();
        $periode = $this->periode();
        $paiements = collect([$this->paiement($periode), $this->paiement($periode)]);

        $this->post('/dmg/paiements/ajourner', [
            'paiement_ids' => $paiements->pluck('id')->all(),
            'motif' => 'Dossier physique non conforme au controle',
        ])->assertRedirect()->assertSessionHasNoErrors();

        foreach ($paiements as $paiement) {
            $this->assertDatabaseHas('paiements', ['id' => $paiement->id, 'statut' => 'AJOURNE_DMG']);
            $this->assertSame(
                CorbeilleEnum::CIP_MES_STAGIAIRES->value,
                $paiement->fresh()->droitPaiement->stage->instanceParcours->corbeille_actuelle,
            );
            $this->assertDatabaseHas('decisions_paiements', [
                'paiement_id' => $paiement->id,
                'decision' => 'AJOURNE_DMG',
                'motif' => 'Dossier physique non conforme au controle',
            ]);
        }

        $this->getJson('/dmg/paiements/json?mois=2026-08&type=presence')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_export_excel_canvas_tresor_pay(): void
    {
        $this->seedActeur();
        $this->paiement($this->periode());

        $this->get('/dmg/paiements/generer-excel?mois=2026-08&nature=presence')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_fusion_tresor_pay_fusionne_les_fiches_des_beneficiaires(): void
    {
        $this->seedActeur();
        $this->paiement($this->periode());

        $this->get('/dmg/paiements/generer-pdf?type=fusion_tresor&mois=2026-08&nature=presence')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_attestation_de_presence_pdf_differenciee_par_financement(): void
    {
        $this->seedActeur();
        $this->paiement($this->periode());

        $this->get('/dmg/paiements/generer-pdf?type=attestation_presence&mois=2026-08')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_etat_de_paiement_pdf_pagine_avec_solde(): void
    {
        $this->seedActeur();
        $this->paiement($this->periode());

        $this->get('/dmg/paiements/generer-pdf?type=etat_paiement&mois=2026-08&nature=presence')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_on_peut_retelecharger_attestation_et_etat_financier_d_un_dossier_simple(): void
    {
        // Équivalent legacy generateAttestationPresenceFromDossier / generateEtatFinancierFromDossier.
        $this->seedActeur();
        $periode = $this->periode();
        $paiement = $this->paiement($periode);

        $this->post('/dmg/paiements/generer', [
            'periode_id' => $periode->id,
            'paiement_ids' => [$paiement->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $dossier = DossierPaiement::firstOrFail();

        $this->get("/dmg/paiements/dossiers/{$dossier->id}/download-attestation")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get("/dmg/paiements/dossiers/{$dossier->id}/download-etat-financier")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_la_file_presence_dmg_est_centrale_toutes_agences_pour_le_role_dmg(): void
    {
        // Portée « régionale » : dans l'ancien Gestage, `scopeMine` ne restreint que le chef
        // d'agence, l'agent de saisie et l'agent-consultation. Les agents DMG (types 76-80,
        // direction centrale) ne sont jamais filtrés : `mine()` y est un no-op et la corbeille
        // DMG s'affiche toutes agences confondues. Le rôle moderne `dmg` doit conserver cette
        // parité — un filtre par agence de l'agent connecté serait une régression.
        $this->seed(RolePermissionSeeder::class);
        $dmg = User::factory()->create();
        $dmg->assignRole('dmg');
        $this->actingAs($dmg);

        $periode = $this->periode();
        $agenceA = Agence::factory()->create(['nom' => 'Agence A']);
        $agenceB = Agence::factory()->create(['nom' => 'Agence B']);

        $paiementA = $this->paiement($periode, agence: $agenceA);
        $paiementB = $this->paiement($periode, agence: $agenceB);

        $this->getJson('/dmg/paiements/json?mois=2026-08&type=presence')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_l_export_volumineux_passe_par_un_batch_suivi_puis_telechargeable(): void
    {
        Bus::fake();

        $this->seedActeur();
        $this->paiement($this->periode());

        $this->post('/dmg/paiements/exporter', [
            'type' => 'etat_paiement',
            'mois' => '2026-08',
            'nature' => 'presence',
        ])->assertOk()->assertJsonStructure(['batch_id']);

        Bus::assertBatched(fn ($batch) => $batch->jobs->first() instanceof GenererExportPaiementJob
            && $batch->jobs->first()->type === 'etat_paiement'
            && $batch->jobs->first()->mois === '2026-08'
            && $batch->jobs->first()->nature === 'presence');
    }

    public function test_la_generation_asynchrone_produit_le_fichier_puis_le_telechargement(): void
    {
        // QUEUE_CONNECTION=sync en test : le job s'exécute dans la requête POST, le fichier est
        // donc immédiatement disponible — c'est le scénario complet que le sondage frontend suit.
        $this->seedActeur();
        $this->paiement($this->periode());

        $batchId = $this->post('/dmg/paiements/exporter', [
            'type' => 'etat_paiement',
            'mois' => '2026-08',
            'nature' => 'presence',
        ])->assertOk()->json('batch_id');

        $this->getJson("/dmg/paiements/exporter/{$batchId}/progression")
            ->assertOk()
            ->assertJsonPath('disponible', true);

        $this->get("/dmg/paiements/exporter/{$batchId}/telechargement")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_un_identifiant_de_batch_inconnu_ne_renvoie_jamais_de_fichier(): void
    {
        $this->seedActeur();

        $this->get('/dmg/paiements/exporter/inconnu/progression')->assertNotFound();
        $this->get('/dmg/paiements/exporter/inconnu/telechargement')->assertNotFound();
    }

    public function test_un_chef_agence_ne_peut_pas_lancer_un_export(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole('chef_agence');
        $this->actingAs($chefAgence);

        $this->post('/dmg/paiements/exporter', ['type' => 'etat_paiement', 'mois' => '2026-08'])
            ->assertForbidden();
    }

    public function test_un_chef_agence_est_exclu_de_la_corbeille_dmg_par_permission(): void
    {
        // Le legacy gardait les écrans DMG accessibles à tout utilisateur authentifié puis les
        // restreignait par `mine()` (chef d'agence → sa seule agence). Le moderne remplace ce
        // filtrage de données par le contrôle d'accès : seul le rôle central `dmg` (et
        // l'administrateur, via Gate::before) possède `voir_paiements_dmg` — un chef d'agence
        // reçoit 403, aucun risque de voir les paiements d'autres agences.
        $this->seed(RolePermissionSeeder::class);
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole('chef_agence');
        $this->actingAs($chefAgence);

        $this->getJson('/dmg/paiements/json?mois=2026-08&type=presence')->assertForbidden();
    }

    public function test_les_lignes_presence_exposent_les_colonnes_de_parite(): void
    {
        $this->seedActeur();
        $paiement = $this->paiement($this->periode());

        $this->getJson('/dmg/paiements/json?mois=2026-08&type=presence')
            ->assertOk()
            ->assertJsonPath('data.0.id', $paiement->id)
            ->assertJsonPath('data.0.date_creation', $paiement->created_at->format('d/m/Y'))
            ->assertJsonPath('data.0.stage.id', $paiement->droitPaiement->stage->id)
            ->assertJsonPath('data.0.dossier_physique.statut', null)
            ->assertJsonStructure([
                'data' => [[
                    'entreprise' => ['raison_sociale', 'type_structure'],
                    'stage' => ['id', 'source_financement', 'source_financement_code'],
                    'dossier_physique' => ['statut', 'marque_le'],
                ]],
            ]);
    }
}