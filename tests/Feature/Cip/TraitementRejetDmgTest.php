<?php

namespace Tests\Feature\Cip;

use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DecisionPaiement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SituationStage;
use App\Models\Reference\TypePaiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Traitement CIP d'un pointage rejeté par la DMG : correction seule, transmission seule,
 * ou les deux d'un coup (portage de `ChefAgence\AttestationPresenceController@updateStagiaire`).
 */
class TraitementRejetDmgTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_formulaire_expose_le_motif_du_rejet_et_les_referentiels(): void
    {
        ['user' => $user, 'stage' => $stage] = $this->scenarioRejetDmg();

        $this->actingAs($user)->get("/cip/pointages/edit-stagiaire/{$stage->id}?return_tab=ajourne_dmg&mois=2026-08")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cip/Pointages/EditStagiaire')
                ->where('rejet.motif', 'Numéro Trésor Money erroné')
                ->where('peutTransmettre', true)
                ->where('returnTo.tab', 'ajourne_dmg')
                ->has('references.typesPaiement')
                ->has('references.communes')
                ->has('typesDocument', 10));
    }

    public function test_corriger_seul_enregistre_la_fiche_sans_transmettre(): void
    {
        ['user' => $user, 'stage' => $stage, 'pointage' => $pointage] = $this->scenarioRejetDmg();

        $this->actingAs($user)
            ->post("/cip/pointages/update-stagiaire/{$stage->id}", $this->payload(['action' => 'enregistrer']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('beneficiaires', [
            'id' => $stage->beneficiaire_id,
            'telephone_principal' => '0700000000',
            'numero_tresor_money' => '0102030405',
            // L'autre canal est purgé : c'est la coordonnée périmée qui avait motivé le rejet.
            'numero_wave' => null,
        ]);
        $this->assertDatabaseHas('stages', ['id' => $stage->id, 'intitule_poste' => 'Assistant comptable']);

        // Sans transmission, le pointage reste dans l'onglet « Ajourné / DMG ».
        $this->assertDatabaseHas('pointages', ['id' => $pointage->id, 'statut' => 'VALIDE']);
        $this->assertDatabaseMissing('decisions_pointages', ['pointage_id' => $pointage->id, 'decision' => 'CORRIGE_CIP']);
    }

    public function test_corriger_et_transmettre_en_une_action_renvoie_le_pointage_au_chef_agence(): void
    {
        ['user' => $user, 'stage' => $stage, 'pointage' => $pointage, 'paiement' => $paiement] = $this->scenarioRejetDmg();

        $this->actingAs($user)
            ->post("/cip/pointages/update-stagiaire/{$stage->id}", $this->payloadComplet([
                'action' => 'enregistrer_transmettre',
                'motif' => 'Numéro Trésor Money rectifié',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('beneficiaires', ['id' => $stage->beneficiaire_id, 'numero_tresor_money' => '0102030405']);
        $this->assertDatabaseHas('pointages', ['id' => $pointage->id, 'statut' => 'CORRIGE_CIP']);
        $this->assertDatabaseHas('decisions_pointages', [
            'pointage_id' => $pointage->id,
            'decision' => 'CORRIGE_CIP',
            'motif' => 'Numéro Trésor Money rectifié',
        ]);
        // Trace côté paiement : le statut ne bouge pas tant que le CA n'a pas re-validé.
        $this->assertDatabaseHas('decisions_paiements', [
            'paiement_id' => $paiement->id,
            'decision' => 'CORRIGE_CIP',
            'statut_avant' => 'AJOURNE_DMG',
            'statut_apres' => 'AJOURNE_DMG',
        ]);
        $this->assertDatabaseHas('paiements', ['id' => $paiement->id, 'statut' => 'AJOURNE_DMG']);
    }

    public function test_transmettre_seul_fonctionne_sur_une_fiche_deja_corrigee(): void
    {
        ['user' => $user, 'stage' => $stage, 'pointage' => $pointage] = $this->scenarioRejetDmg();

        $this->actingAs($user)
            ->post("/cip/pointages/transmettre-correction-dmg/{$stage->id}", ['motif' => 'Corrigé hier'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pointages', ['id' => $pointage->id, 'statut' => 'CORRIGE_CIP']);
        $this->assertDatabaseHas('decisions_pointages', ['pointage_id' => $pointage->id, 'decision' => 'CORRIGE_CIP']);
    }

    public function test_un_cip_dune_autre_agence_ne_peut_ni_ouvrir_ni_modifier_la_fiche(): void
    {
        ['stage' => $stage] = $this->scenarioRejetDmg();
        $intrus = User::factory()->create();
        $intrus->perimetresAgences()->attach(Agence::factory()->create()->id);

        $this->actingAs($intrus)
            ->get("/cip/pointages/edit-stagiaire/{$stage->id}")
            ->assertForbidden();

        $this->actingAs($intrus)
            ->post("/cip/pointages/update-stagiaire/{$stage->id}", $this->payload())
            ->assertForbidden();

        $this->actingAs($intrus)
            ->post("/cip/pointages/transmettre-correction-dmg/{$stage->id}", ['motif' => 'Tentative'])
            ->assertForbidden();
    }

    public function test_un_administrateur_accede_a_la_fiche_hors_de_tout_perimetre(): void
    {
        ['stage' => $stage] = $this->scenarioRejetDmg();

        // L'administrateur a une visibilité nationale : aucun périmètre agence ne lui est attaché.
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'administrateur', 'guard_name' => 'web']));

        $this->actingAs($admin)
            ->get("/cip/pointages/edit-stagiaire/{$stage->id}")
            ->assertOk();
    }

    public function test_la_transmission_est_refusee_quand_aucun_pointage_nest_ajourne(): void
    {
        ['user' => $user, 'stage' => $stage, 'pointage' => $pointage, 'paiement' => $paiement] = $this->scenarioRejetDmg();

        // Le paiement n'est plus ajourné : il n'y a donc plus rien à corriger côté CIP.
        $paiement->update(['statut' => 'A_TRAITER']);

        $this->actingAs($user)
            ->post("/cip/pointages/transmettre-correction-dmg/{$stage->id}", ['motif' => 'Tentative'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('pointages', ['id' => $pointage->id, 'statut' => 'VALIDE']);
        $this->assertDatabaseMissing('decisions_pointages', ['pointage_id' => $pointage->id, 'decision' => 'CORRIGE_CIP']);
    }

    public function test_la_correction_seule_reste_possible_sur_une_fiche_incomplete(): void
    {
        ['user' => $user, 'stage' => $stage, 'pointage' => $pointage] = $this->scenarioRejetDmg();

        // Correction en plusieurs passes : le CIP n'a encore rectifié que le canal de paiement.
        $this->actingAs($user)
            ->post("/cip/pointages/update-stagiaire/{$stage->id}", $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Mais la même fiche partielle ne peut pas repartir chez le Chef d'Agence.
        $this->actingAs($user)
            ->post("/cip/pointages/update-stagiaire/{$stage->id}", $this->payload([
                'action' => 'enregistrer_transmettre',
            ]))
            ->assertSessionHasErrors(['observations', 'situation_stage', 'nom_encadreur']);

        $this->assertDatabaseHas('pointages', ['id' => $pointage->id, 'statut' => 'VALIDE']);
    }

    public function test_le_redepot_dune_piece_ajoute_une_version_au_document(): void
    {
        Storage::fake('public');
        ['user' => $user, 'stage' => $stage] = $this->scenarioRejetDmg();

        $this->actingAs($user)
            ->post("/cip/pointages/update-stagiaire/{$stage->id}", $this->payload([
                'action' => 'enregistrer',
                'documents' => ['CONTRAT' => UploadedFile::fake()->create('contrat.pdf', 12, 'application/pdf')],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('types_document', ['code' => 'CONTRAT']);
        $this->assertDatabaseHas('documents', ['stage_id' => $stage->id, 'nom' => 'contrat.pdf']);
        $this->assertDatabaseHas('versions_documents', ['numero_version' => 1, 'nom_original' => 'contrat.pdf']);
    }

    public function test_le_numero_du_canal_choisi_est_obligatoire(): void
    {
        ['user' => $user, 'stage' => $stage] = $this->scenarioRejetDmg();

        $this->actingAs($user)
            ->post("/cip/pointages/update-stagiaire/{$stage->id}", $this->payload([
                'action' => 'enregistrer',
                'numero_tresor_money' => '',
            ]))
            ->assertSessionHasErrors('numero_tresor_money');
    }

    /**
     * Jeu de données minimal reproduisant l'onglet « Ajourné / DMG » : paiement AJOURNE_DMG
     * dont le pointage est encore VALIDE.
     *
     * @return array{user: User, stage: Stage, pointage: Pointage, paiement: Paiement}
     */
    private function scenarioRejetDmg(): array
    {
        $stage = Stage::factory()->create();
        // Le CIP doit relever de l'agence du stagiaire, sinon le périmètre le renvoie en 403.
        $user = User::factory()->create();
        $user->perimetresAgences()->attach($stage->agence_id);
        $periode = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
            'ouverte_pointage' => true,
            'ouverte_paiement' => true,
        ]);

        $pointage = Pointage::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'nature' => 'MENSUEL',
            'statut' => 'VALIDE',
            'version_courante' => 1,
            'version_verrouillage' => 0,
        ]);

        VersionPointage::create([
            'pointage_id' => $pointage->id,
            'saisi_par_id' => $user->id,
            'numero_version' => 1,
            'presence' => 'PRESENT',
            'jours_presents' => 20,
            'jours_absents' => 0,
        ]);

        $droit = DroitPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'pointage_id' => $pointage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $stage->source_financement_id,
            'nature' => 'PRESENCE',
            'montant' => 45000,
            'statut' => 'OUVERT',
        ]);

        $paiement = Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'droit_paiement_id' => $droit->id,
            'montant' => 45000,
            'statut' => 'AJOURNE_DMG',
        ]);

        DecisionPaiement::enregistrer($paiement, $user, 'AJOURNE_DMG', 'Numéro Trésor Money erroné', 'A_TRAITER', 'AJOURNE_DMG');

        $stage->beneficiaire->update(['numero_wave' => '0500000000']);

        return ['user' => $user, 'stage' => $stage, 'pointage' => $pointage, 'paiement' => $paiement];
    }

    /**
     * @param  array<string, mixed>  $surcharges
     * @return array<string, mixed>
     */
    private function payload(array $surcharges = []): array
    {
        $stage = Stage::with('beneficiaire')->latest('id')->firstOrFail();
        $tresorMoney = TypePaiement::firstOrCreate(
            ['code' => TypePaiement::CODE_TRESOR_MONEY],
            ['nom' => 'TRESOR MONEY', 'actif' => true]
        );

        return array_merge([
            'action' => 'enregistrer',
            'nom' => $stage->beneficiaire->nom,
            'prenoms' => $stage->beneficiaire->prenoms,
            'telephone_principal' => '0700000000',
            'type_paiement_id' => $tresorMoney->id,
            'numero_tresor_money' => '0102030405',
            'entreprise_id' => $stage->entreprise_id,
            'type_stage_id' => $stage->type_stage_id,
            'intitule_poste' => 'Assistant comptable',
            'date_debut' => '2026-08-01',
            'date_fin_prevue' => '2027-07-31',
            'return_tab' => 'ajourne_dmg',
            'mois' => '2026-08',
        ], $surcharges);
    }

    /**
     * Payload complet exigé par la transmission : contrairement à la correction seule, le
     * renvoi au Chef d'Agence n'accepte pas une fiche partielle.
     *
     * @param  array<string, mixed>  $surcharges
     * @return array<string, mixed>
     */
    private function payloadComplet(array $surcharges = []): array
    {
        $stage = Stage::with('beneficiaire')->latest('id')->firstOrFail();

        return $this->payload(array_merge([
            'agence_id' => $stage->agence_id,
            'source_financement_id' => $stage->source_financement_id,

            'sexe' => 'M',
            'date_naissance' => '1998-04-12',
            'lieu_naissance' => 'Abidjan',
            'sous_prefecture_naissance' => 'Abidjan',
            'sous_prefecture_residence' => 'Cocody',
            'nature_piece_identite' => 'CNI',
            'numero_piece_identite' => 'CI0012345678',
            'numero_cmu' => 'CMU0012345',

            'personne_urgence' => 'Kouassi Aya',
            'lien_parente_id' => $this->referenceId('liens_parente', 'LP-MERE', 'Mère'),
            'contact_urgence_1' => '0501020304',

            'niveau_etude_id' => $this->referenceId('niveaux_etude', 'NE-BAC', 'Baccalauréat'),
            'diplome_id' => $this->referenceId('diplomes', 'DIP-BTS', 'BTS'),
            'specialite' => 'Comptabilité',
            'etablissement_frequente' => 'INP-HB',
            'type_enseignement_id' => $this->referenceId('types_enseignement', 'TE-GENERAL', 'Général'),
            'handicap_id' => $this->referenceId('handicaps', 'HD-AUCUN', 'Aucun'),

            'service_affectation' => 'Comptabilité',
            'localite_stage' => 'Abidjan',
            'commune_stage' => 'Plateau',
            'sous_prefecture_stage' => 'Abidjan',
            'nom_encadreur' => 'Traoré Salif',
            'fonction_encadreur' => 'Chef comptable',
            'contact_encadreur' => '0708091011',
            'situation_stage' => $this->referenceCode('situations_stage', SituationStage::CODE_EN_COURS, 'En cours'),
            'observations' => 'Numéro Trésor Money corrigé.',
        ], $surcharges));
    }

    /**
     * Les référentiels à code (`code`/`nom`/`actif`) partagent le même schéma.
     */
    private function referenceCode(string $table, string $code, string $nom): string
    {
        $this->referenceId($table, $code, $nom);

        return $code;
    }

    private function referenceId(string $table, string $code, string $nom): int
    {
        $existant = DB::table($table)->where('code', $code)->value('id');

        return (int) ($existant ?? DB::table($table)->insertGetId([
            'code' => $code,
            'nom' => $nom,
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
}
