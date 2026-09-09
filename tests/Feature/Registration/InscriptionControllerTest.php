<?php

namespace Tests\Feature\Registration;

use App\Enums\CorbeilleEnum;
use App\Enums\DoublonTypeEnum;
use App\Models\Company\Entreprise;
use App\Models\Company\OffreEmploi;
use App\Models\Document\Document;
use App\Models\Document\VersionDocument;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeDocument;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\DesseDoublonDecision;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\EvenementParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cip;

    private OffreEmploi $offre;

    protected function setUp(): void
    {
        parent::setUp();

        // Rôle et permissions calqués sur RolePermissionSeeder : la consultation d'un
        // dossier exige désormais la permission `voir_beneficiaires` côté serveur.
        $roleCIP = Role::firstOrCreate(['name' => 'cip']);
        $roleCIP->givePermissionTo(
            Permission::firstOrCreate(['name' => 'voir_beneficiaires', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'gerer_beneficiaires', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'voir_contrats', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'voir_pointages', 'guard_name' => 'web']),
        );
        $this->cip = User::factory()->create();
        $this->cip->assignRole($roleCIP);

        $definition = DefinitionParcours::factory()->create(['code' => 'PAE', 'active' => true]);
        EtapeParcours::factory()->create([
            'definition_parcours_id' => $definition->id,
            'role_responsable_id' => $roleCIP->id,
            'initiale' => true,
        ]);

        $agence = Agence::factory()->create();
        $entreprise = Entreprise::factory()->create(['agence_id' => $agence->id]);
        $typeStage = TypeStage::factory()->create();
        $source = SourceFinancement::factory()->create();

        $this->offre = OffreEmploi::factory()->create([
            'entreprise_id' => $entreprise->id,
            'agence_id' => $agence->id,
            'type_stage_id' => $typeStage->id,
            'source_financement_id' => $source->id,
            'statut' => 'PUBLIEE',
        ]);
    }

    public function test_cip_peut_voir_index(): void
    {
        $response = $this->actingAs($this->cip)->get('/inscriptions');

        $response->assertStatus(200);
    }

    public function test_cip_peut_voir_formulaire_creation(): void
    {
        $response = $this->actingAs($this->cip)->get('/inscriptions/create');

        $response->assertStatus(200);
    }

    public function test_cip_peut_inscrire_stagiaire(): void
    {
        $payload = [
            'beneficiaire' => [
                'numero_aej' => 'AEJ-123456',
                'nom' => 'Doe',
                'prenoms' => 'John',
                'date_naissance' => '2000-01-01',
                'sexe' => 'M',
            ],
            'stage' => [
                'entreprise_id' => $this->offre->entreprise_id,
                'agence_id' => $this->offre->agence_id,
                'type_stage_id' => $this->offre->type_stage_id,
                'source_financement_id' => $this->offre->source_financement_id,
                'offre_emploi_id' => $this->offre->id,
                'intitule_poste' => 'Développeur Web',
                'date_debut' => '2026-09-01',
                'date_fin_prevue' => '2027-02-28',
            ],
            'contrat' => [
                'numero' => 'CTR-2026-0001',
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-02-28',
                'prime_mensuelle' => 45000,
            ],
        ];

        $response = $this->actingAs($this->cip)->post('/inscriptions', $payload);

        $response->assertRedirect('/inscriptions');
        $this->assertDatabaseHas('beneficiaires', ['nom' => 'Doe']);
    }

    public function test_le_detail_du_dossier_expose_la_corbeille_actuelle(): void
    {
        $this->actingAs($this->cip)->post('/inscriptions', [
            'beneficiaire' => [
                'numero_aej' => 'AEJ-999999',
                'nom' => 'Kouassi',
                'prenoms' => 'Awa',
                'date_naissance' => '2000-01-01',
                'sexe' => 'F',
            ],
            'stage' => [
                'entreprise_id' => $this->offre->entreprise_id,
                'agence_id' => $this->offre->agence_id,
                'type_stage_id' => $this->offre->type_stage_id,
                'source_financement_id' => $this->offre->source_financement_id,
                'offre_emploi_id' => $this->offre->id,
                'intitule_poste' => 'Développeur Web',
                'date_debut' => '2026-09-01',
                'date_fin_prevue' => '2027-02-28',
            ],
            'contrat' => [
                'numero' => 'CTR-2026-0002',
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-02-28',
                'prime_mensuelle' => 45000,
            ],
        ]);

        $instance = InstanceParcours::whereHas('stage.beneficiaire', fn ($q) => $q->where('numero_aej', 'AEJ-999999'))->firstOrFail();

        $this->actingAs($this->cip)->get("/inscriptions/{$instance->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inscriptions/Show')
                ->has('corbeilleActuelle')
                ->has('suiviPointages')
                ->has('doublons')
            );
    }

    public function test_un_chef_agence_peut_ouvrir_et_enregistrer_le_formulaire_edition(): void
    {
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole($this->roleChefAgence());
        $chefAgence->perimetresAgences()->attach($this->offre->agence_id);

        $this->actingAs($this->cip)->post('/inscriptions', [
            'beneficiaire' => [
                'numero_aej' => 'AEJ-817350', 'nom' => 'Initial', 'prenoms' => 'Awa',
                'date_naissance' => '2000-01-01', 'sexe' => 'F',
            ],
            'stage' => [
                'entreprise_id' => $this->offre->entreprise_id, 'agence_id' => $this->offre->agence_id,
                'type_stage_id' => $this->offre->type_stage_id, 'source_financement_id' => $this->offre->source_financement_id,
                'offre_emploi_id' => $this->offre->id, 'intitule_poste' => 'Développeuse',
                'date_debut' => '2026-09-01', 'date_fin_prevue' => '2027-02-28',
            ],
            'contrat' => ['numero' => 'CTR-EDIT-1', 'date_debut' => '2026-09-01', 'date_fin' => '2027-02-28'],
        ]);

        $instance = InstanceParcours::whereHas('stage.beneficiaire', fn ($q) => $q->where('numero_aej', 'AEJ-817350'))->firstOrFail();

        $this->actingAs($chefAgence)->get("/inscriptions/{$instance->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inscriptions/Create')
                ->where('mode', 'edit')
                ->where('inscriptionId', $instance->id)
                ->has('initialData.beneficiaire')
                ->has('initialData.stage')
                ->has('initialData.contrat')
                ->has('initialData.documents')
                ->has('initialData.tachesOuvertes')
                ->has('initialData.evenements')
            );

        $this->actingAs($chefAgence)->put("/inscriptions/{$instance->id}", [
            'beneficiaire' => ['nom' => 'Modifie'],
            'stage' => ['intitule_poste' => 'Poste modifie'],
            'contrat' => ['prime_mensuelle' => 55000],
        ])->assertRedirect("/inscriptions/{$instance->id}");

        $this->assertDatabaseHas('beneficiaires', ['id' => $instance->stage->beneficiaire_id, 'nom' => 'Modifie']);
        $this->assertDatabaseHas('stages', ['id' => $instance->stage_id, 'intitule_poste' => 'Poste modifie']);
        $this->assertDatabaseHas('contrats', ['stage_id' => $instance->stage_id, 'prime_mensuelle' => 55000]);
    }

    public function test_edition_refusee_hors_perimetre_agence(): void
    {
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole($this->roleChefAgence());
        $autreAgence = Agence::factory()->create();
        $chefAgence->perimetresAgences()->attach($autreAgence->id);

        $instance = $this->creerInscription('AEJ-403000');

        $this->actingAs($chefAgence)->get("/inscriptions/{$instance->id}/edit")->assertForbidden();
        $this->actingAs($chefAgence)->put("/inscriptions/{$instance->id}", [
            'beneficiaire' => ['nom' => 'Interdit'],
            'stage' => [],
            'contrat' => [],
        ])->assertForbidden();

        $this->assertDatabaseHas('beneficiaires', ['id' => $instance->stage->beneficiaire_id, 'nom' => 'Initial']);
    }

    public function test_depot_document_lors_edition(): void
    {
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole($this->roleChefAgence());
        $chefAgence->perimetresAgences()->attach($this->offre->agence_id);

        $instance = $this->creerInscription('AEJ-DOC0001');

        $this->actingAs($chefAgence)->put("/inscriptions/{$instance->id}", [
            'beneficiaire' => [],
            'stage' => [],
            'contrat' => [],
            'documents' => [
                'piece_identite' => \Illuminate\Http\UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect("/inscriptions/{$instance->id}");

        $this->assertDatabaseHas('documents', ['stage_id' => $instance->stage_id, 'nom' => 'cni.pdf']);
    }

    public function test_un_document_du_dossier_peut_etre_telecharge(): void
    {
        Storage::fake('public');
        $instance = $this->creerInscription('AEJ-DOWNLOAD-1');
        $typeDocument = TypeDocument::create(['code' => 'TEST_PIECE', 'nom' => 'Pièce de test', 'actif' => true]);
        $document = Document::create([
            'type_document_id' => $typeDocument->id,
            'stage_id' => $instance->stage_id,
            'beneficiaire_id' => $instance->stage->beneficiaire_id,
            'nom' => 'piece.pdf',
            'statut' => 'VALIDE',
            'prive' => true,
        ]);
        Storage::disk('public')->put('dossiers/piece.pdf', 'contenu');
        VersionDocument::create([
            'document_id' => $document->id,
            'numero_version' => 1,
            'disque' => 'public',
            'chemin' => 'dossiers/piece.pdf',
            'nom_original' => 'piece.pdf',
            'type_mime' => 'application/pdf',
            'taille_octets' => 8,
            'empreinte_sha256' => hash('sha256', 'contenu'),
        ]);

        $this->actingAs($this->cip)
            ->get("/inscriptions/{$instance->id}/documents/{$document->id}/download")
            ->assertOk()
            ->assertDownload('piece.pdf');
    }

    public function test_un_document_d_un_autre_dossier_est_inaccessible(): void
    {
        $instance = $this->creerInscription('AEJ-DOWNLOAD-2');
        $autre = $this->creerInscription('AEJ-DOWNLOAD-3');
        $typeDocument = TypeDocument::create(['code' => 'TEST_PIECE_AUTRE', 'nom' => 'Pièce de test autre', 'actif' => true]);
        $document = Document::create([
            'type_document_id' => $typeDocument->id,
            'stage_id' => $autre->stage_id,
            'beneficiaire_id' => $autre->stage->beneficiaire_id,
            'nom' => 'autre.pdf',
            'statut' => 'VALIDE',
            'prive' => true,
        ]);

        $this->actingAs($this->cip)
            ->get("/inscriptions/{$instance->id}/documents/{$document->id}/download")
            ->assertNotFound();
    }

    public function test_rollback_transactionnel_si_erreur_lors_edition(): void
    {
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole($this->roleChefAgence());
        $chefAgence->perimetresAgences()->attach($this->offre->agence_id);

        $autreInstance = $this->creerInscription('AEJ-ROLLBACK-A');
        $instance = $this->creerInscription('AEJ-ROLLBACK-B');

        // Le numéro de contrat de l'autre dossier viole la contrainte unique('numero')
        // en base : la mise à jour du contrat échoue après celle du bénéficiaire, dans
        // la même transaction. Rien ne doit être persisté.
        $response = $this->actingAs($chefAgence)->put("/inscriptions/{$instance->id}", [
            'beneficiaire' => ['nom' => 'NePasPersister'],
            'stage' => [],
            'contrat' => ['numero' => $autreInstance->stage->contrats()->first()->numero],
        ]);

        $response->assertStatus(500);
        $this->assertDatabaseHas('beneficiaires', ['id' => $instance->stage->beneficiaire_id, 'nom' => 'Initial']);
    }

    public function test_un_visiteur_non_authentifie_ne_voit_pas_le_detail(): void
    {
        $instance = $this->creerInscription('AEJ-ANON-001');

        // creerInscription() a authentifié le CIP : actingAs() persiste d'une requête à
        // l'autre dans un même test.
        auth()->logout();
        $this->flushSession();

        $this->get("/inscriptions/{$instance->id}")->assertRedirect('/login');
    }

    public function test_un_role_sans_permission_beneficiaires_ne_voit_pas_le_detail(): void
    {
        Storage::fake('public');
        $instance = $this->creerInscription('AEJ-NOPERM-01');
        $document = $this->attacherDocument($instance, 'DOC_NOPERM', 'interdit.pdf');

        $intrus = User::factory()->create();
        $intrus->assignRole(Role::firstOrCreate(['name' => 'role_sans_droit']));

        $this->actingAs($intrus)->get("/inscriptions/{$instance->id}")->assertForbidden();
        $this->actingAs($intrus)
            ->get("/inscriptions/{$instance->id}/documents/{$document->id}/download")
            ->assertForbidden();
    }

    public function test_le_detail_est_refuse_hors_perimetre_agence(): void
    {
        $instance = $this->creerInscription('AEJ-PERIM-001');

        $cipAutreAgence = User::factory()->create();
        $cipAutreAgence->assignRole(Role::findByName('cip'));
        $cipAutreAgence->perimetresAgences()->attach(Agence::factory()->create()->id);

        $this->actingAs($cipAutreAgence)->get("/inscriptions/{$instance->id}")->assertForbidden();
    }

    public function test_un_cip_consulte_un_dossier_de_son_perimetre(): void
    {
        $instance = $this->creerInscription('AEJ-PERIM-002');

        $cipDuPerimetre = User::factory()->create();
        $cipDuPerimetre->assignRole(Role::findByName('cip'));
        $cipDuPerimetre->perimetresAgences()->attach($this->offre->agence_id);

        $this->actingAs($cipDuPerimetre)->get("/inscriptions/{$instance->id}")->assertOk();
    }

    public function test_un_role_central_consulte_un_dossier_de_toute_agence(): void
    {
        $instance = $this->creerInscription('AEJ-CENTRAL-1');

        // La DESSE n'est pas bornée à un périmètre d'agence : elle contrôle le réseau.
        $desse = User::factory()->create();
        $roleDesse = Role::firstOrCreate(['name' => 'desse']);
        $roleDesse->givePermissionTo(Permission::firstOrCreate(['name' => 'voir_beneficiaires', 'guard_name' => 'web']));
        $desse->assignRole($roleDesse);

        $this->actingAs($desse)->get("/inscriptions/{$instance->id}")->assertOk();
    }

    public function test_un_dossier_inconnu_renvoie_404(): void
    {
        $this->actingAs($this->cip)->get('/inscriptions/999999')->assertNotFound();
    }

    public function test_le_detail_expose_les_metadonnees_ged(): void
    {
        Storage::fake('public');
        $instance = $this->creerInscription('AEJ-GED-0001');
        $auteur = User::factory()->create(['nom' => 'Konan Depositaire']);
        $document = $this->attacherDocument($instance, 'DOC_GED', 'contrat.pdf', $auteur);

        $this->actingAs($this->cip)->get("/inscriptions/{$instance->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inscriptions/Show')
                ->has('documents', 1, fn (Assert $doc) => $doc
                    ->where('id', $document->id)
                    ->where('type', 'Pièce DOC_GED')
                    ->where('nom_original', 'contrat.pdf')
                    ->where('version', 1)
                    ->where('taille_octets', 8)
                    ->where('type_mime', 'application/pdf')
                    ->where('statut', 'VALIDE')
                    ->where('auteur', 'Konan Depositaire')
                    ->where('telechargeable', true)
                    ->where('url', url("/inscriptions/{$instance->id}/documents/{$document->id}/download"))
                    ->etc()
                )
            );
    }

    public function test_le_detail_expose_les_droits_d_action(): void
    {
        $instance = $this->creerInscription('AEJ-DROITS-01');

        // Le CIP consulte mais n'édite pas depuis cette fiche (assertCanEdit).
        $this->actingAs($this->cip)->get("/inscriptions/{$instance->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('droits.peut_modifier', false)
                ->where('droits.peut_telecharger_documents', true)
                ->where('droits.peut_voir_pointages', true)
                ->where('droits.peut_traiter_doublons', false)
                ->has('droits.peut_valider_etape')
                ->etc()
            );

        $chefAgence = User::factory()->create();
        $chefAgence->assignRole($this->roleChefAgence());
        $chefAgence->perimetresAgences()->attach($this->offre->agence_id);

        $this->actingAs($chefAgence)->get("/inscriptions/{$instance->id}")
            ->assertInertia(fn (Assert $page) => $page->where('droits.peut_modifier', true)->etc());
    }

    public function test_le_detail_expose_l_historique_du_parcours(): void
    {
        $instance = $this->creerInscription('AEJ-HISTO-001');
        $etape = EtapeParcours::first();

        EvenementParcours::create([
            'instance_parcours_id' => $instance->id,
            'auteur_id' => $this->cip->id,
            'etape_source_id' => $etape->id,
            'etape_cible_id' => $etape->id,
            'type' => 'TRANSMISSION',
            'cle_idempotence' => 'evt-histo-'.$instance->id,
            'donnees' => ['message' => 'Transmis au Chef d\'Agence'],
            'survenu_le' => now(),
        ]);

        $this->actingAs($this->cip)->get("/inscriptions/{$instance->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // L'inscription a déjà journalisé son propre événement : celui posé
                // ci-dessus est le second.
                ->has('instance.evenements', 2)
                ->has('instance.evenements.1', fn (Assert $evt) => $evt
                    ->where('type', 'TRANSMISSION')
                    ->has('acteur')
                    ->has('etape_source')
                    ->has('etape_cible')
                    ->etc()
                )
                ->etc()
            );
    }

    public function test_le_detail_expose_le_suivi_des_pointages_et_paiements(): void
    {
        $instance = $this->creerInscription('AEJ-SUIVI-001');

        $this->actingAs($this->cip)->get("/inscriptions/{$instance->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('suiviPointages')
                ->has('corbeilleActuelle.code')
                ->has('corbeilleActuelle.label')
                ->etc()
            );
    }

    public function test_aucun_doublon_desse_n_est_affiche_sans_confirmation_du_service(): void
    {
        $instance = $this->creerInscription('AEJ-SANSDBL-1');

        $this->actingAs($this->cip)->get("/inscriptions/{$instance->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('doublons', 0)->etc());
    }

    public function test_un_doublon_desse_confirme_est_expose_avec_l_etat_du_pare_feu(): void
    {
        $premier = $this->creerInscription('AEJ-DBL-0001');
        $second = $this->creerInscription('AEJ-DBL-0002');

        // Même pièce d'identité portée par deux bénéficiaires distincts : c'est la
        // définition du doublon côté DesseDoublonService.
        foreach ([$premier, $second] as $index => $instance) {
            $instance->stage->beneficiaire->update([
                'numero_piece_identite' => 'CI-0099887766',
                // Sans identités distinctes, le service détecterait aussi le doublon
                // « Nom, Prénoms, Date de Naissance et Type de Stage ».
                'nom' => 'Homonyme'.$index,
            ]);
            // Le pool de détection exclut la corbeille de saisie CIP.
            $instance->update(['corbeille_actuelle' => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value]);
        }

        $this->actingAs($this->cip)->get("/inscriptions/{$premier->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('doublons', 1, fn (Assert $doublon) => $doublon
                    ->where('type', DoublonTypeEnum::PIECE_IDENTITE->value)
                    ->where('label', DoublonTypeEnum::PIECE_IDENTITE->label())
                    ->where('cle', 'CI-0099887766')
                    ->where('bloquant', true)
                    ->where('pare_feu', 'ACTIF')
                    // Le CIP n'a pas `valider_desse` : aucun lien vers l'écran DESSE.
                    ->where('lien', null)
                    ->has('message')
                    ->etc()
                )
                ->etc()
            );
    }

    public function test_un_doublon_desse_tranche_leve_le_pare_feu(): void
    {
        $premier = $this->creerInscription('AEJ-DBLT-001');
        $second = $this->creerInscription('AEJ-DBLT-002');

        foreach ([$premier, $second] as $index => $instance) {
            $instance->stage->beneficiaire->update([
                'numero_piece_identite' => 'CI-1122334455',
                'nom' => 'Tranche'.$index,
            ]);
            $instance->update(['corbeille_actuelle' => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value]);
        }

        DesseDoublonDecision::create([
            'instance_parcours_id' => $premier->id,
            'type_doublon' => DoublonTypeEnum::PIECE_IDENTITE->value,
            'cle_doublon' => 'CI-1122334455',
            'decision' => 'NON_AVERE',
            'motif' => 'Pièces distinctes après contrôle',
            'decide_par_id' => $this->cip->id,
            'decide_le' => now(),
        ]);

        $this->actingAs($this->cip)->get("/inscriptions/{$premier->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('doublons', 1, fn (Assert $doublon) => $doublon
                    ->where('bloquant', false)
                    ->where('pare_feu', 'LEVE')
                    ->where('decision', 'NON_AVERE')
                    ->where('motif', 'Pièces distinctes après contrôle')
                    ->etc()
                )
                ->etc()
            );
    }

    public function test_le_detail_supporte_les_valeurs_nulles(): void
    {
        $instance = $this->creerInscription('AEJ-NULL-0001');

        // Dossier dépouillé : ni référentiels optionnels, ni document, ni événement.
        $instance->stage->beneficiaire->update([
            'lieu_naissance' => null, 'email' => null, 'telephone_principal' => null,
            'numero_cmu' => null, 'commune_residence_id' => null, 'niveau_etude_id' => null,
            'diplome_id' => null, 'handicap_id' => null, 'type_paiement_id' => null,
        ]);
        $instance->stage->update(['conseiller_id' => null, 'programme_id' => null, 'nom_encadreur' => null]);

        $this->actingAs($this->cip)->get("/inscriptions/{$instance->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Inscriptions/Show')->has('documents', 0)->etc());
    }

    public function test_le_telechargement_est_refuse_hors_perimetre_agence(): void
    {
        Storage::fake('public');
        $instance = $this->creerInscription('AEJ-DLPERIM-1');
        $document = $this->attacherDocument($instance, 'DOC_PERIM', 'confidentiel.pdf');

        $cipAutreAgence = User::factory()->create();
        $cipAutreAgence->assignRole(Role::findByName('cip'));
        $cipAutreAgence->perimetresAgences()->attach(Agence::factory()->create()->id);

        $this->actingAs($cipAutreAgence)
            ->get("/inscriptions/{$instance->id}/documents/{$document->id}/download")
            ->assertForbidden();
    }

    public function test_l_edition_rejette_une_charge_utile_invalide(): void
    {
        $chefAgence = User::factory()->create();
        $chefAgence->assignRole($this->roleChefAgence());
        $chefAgence->perimetresAgences()->attach($this->offre->agence_id);

        $instance = $this->creerInscription('AEJ-422-0001');

        $this->actingAs($chefAgence)
            ->put("/inscriptions/{$instance->id}", [
                'beneficiaire' => ['email' => 'pas-un-email', 'sexe' => 'X'],
                'stage' => [],
                'contrat' => [],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['beneficiaire.email', 'beneficiaire.sexe']);
    }

    public function test_le_detail_ne_declenche_pas_de_n_plus_un_sur_les_documents(): void
    {
        Storage::fake('public');
        $unDocument = $this->creerInscription('AEJ-NPLUS1-A');
        $this->attacherDocument($unDocument, 'DOC_N1_A', 'a1.pdf');

        $plusieursDocuments = $this->creerInscription('AEJ-NPLUS1-B');
        foreach (range(1, 6) as $i) {
            $this->attacherDocument($plusieursDocuments, "DOC_N1_B{$i}", "b{$i}.pdf");
        }

        // Amorçage (cache des permissions, session) hors mesure.
        $this->actingAs($this->cip)->get("/inscriptions/{$unDocument->id}")->assertOk();

        $requetesUnDocument = $this->compterRequetes(fn () => $this->actingAs($this->cip)
            ->get("/inscriptions/{$unDocument->id}")->assertOk());

        $requetesPlusieursDocuments = $this->compterRequetes(fn () => $this->actingAs($this->cip)
            ->get("/inscriptions/{$plusieursDocuments->id}")->assertOk());

        // Documents, versions et auteurs sont eager loadés : passer de 1 à 6 pièces ne
        // doit pas ajouter de requête par pièce.
        $this->assertLessThanOrEqual(
            $requetesUnDocument,
            $requetesPlusieursDocuments,
            "Le nombre de requêtes croît avec le nombre de documents : {$requetesUnDocument} -> {$requetesPlusieursDocuments}.",
        );
    }

    private function compterRequetes(callable $callback): int
    {
        $requetes = 0;
        DB::listen(function () use (&$requetes): void {
            $requetes++;
        });

        $callback();

        return $requetes;
    }

    private function roleChefAgence(): Role
    {
        $role = Role::firstOrCreate(['name' => 'chef_agence']);
        $role->givePermissionTo(
            Permission::firstOrCreate(['name' => 'voir_beneficiaires', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'voir_contrats', 'guard_name' => 'web']),
        );

        return $role;
    }

    private function attacherDocument(InstanceParcours $instance, string $code, string $nom, ?User $auteur = null): Document
    {
        $typeDocument = TypeDocument::firstOrCreate(
            ['code' => $code],
            ['nom' => 'Pièce '.$code, 'actif' => true],
        );

        $document = Document::create([
            'type_document_id' => $typeDocument->id,
            'stage_id' => $instance->stage_id,
            'beneficiaire_id' => $instance->stage->beneficiaire_id,
            'nom' => $nom,
            'statut' => 'VALIDE',
            'prive' => true,
        ]);

        Storage::disk('public')->put("dossiers/{$nom}", 'contenu');

        VersionDocument::create([
            'document_id' => $document->id,
            'depose_par_id' => $auteur?->id,
            'numero_version' => 1,
            'disque' => 'public',
            'chemin' => "dossiers/{$nom}",
            'nom_original' => $nom,
            'type_mime' => 'application/pdf',
            'taille_octets' => 8,
            'empreinte_sha256' => hash('sha256', 'contenu'),
        ]);

        return $document;
    }

    private function creerInscription(string $numeroAej): InstanceParcours
    {
        $this->actingAs($this->cip)->post('/inscriptions', [
            'beneficiaire' => [
                'numero_aej' => $numeroAej, 'nom' => 'Initial', 'prenoms' => 'Awa',
                'date_naissance' => '2000-01-01', 'sexe' => 'F',
            ],
            'stage' => [
                'entreprise_id' => $this->offre->entreprise_id, 'agence_id' => $this->offre->agence_id,
                'type_stage_id' => $this->offre->type_stage_id, 'source_financement_id' => $this->offre->source_financement_id,
                'offre_emploi_id' => $this->offre->id, 'intitule_poste' => 'Développeuse',
                'date_debut' => '2026-09-01', 'date_fin_prevue' => '2027-02-28',
            ],
            'contrat' => ['numero' => 'CTR-'.$numeroAej, 'date_debut' => '2026-09-01', 'date_fin' => '2027-02-28'],
        ]);

        return InstanceParcours::whereHas('stage.beneficiaire', fn ($q) => $q->where('numero_aej', $numeroAej))->firstOrFail();
    }
}
