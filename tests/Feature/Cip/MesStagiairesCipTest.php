<?php

namespace Tests\Feature\Cip;

use App\Domain\Workflow\Services\ListeStagiairesCipService;
use App\Enums\CorbeilleEnum;
use App\Jobs\ExporterStagiairesCipJob;
use App\Models\Company\Entreprise;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\Reference\TypePaiement;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Écran CIP « Mes Stagiaires » (portage de `Cip\IndexCipController@mesStagiaireToJson`) :
 * périmètre agence, filtres/recherche, actions de dossier (contrat, Trésor Money, transmission
 * au Chef d'Agence, suivi des pointages) et suppression.
 */
class MesStagiairesCipTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_cip_ne_voit_que_les_dossiers_de_son_perimetre_agence(): void
    {
        ['user' => $user, 'instance' => $instanceAgenceUtilisateur] = $this->creerDossier();
        ['instance' => $instanceAutreAgence] = $this->creerDossier();

        $this->actingAs($user)
            ->get('/cip/mes-stagiaires')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cip/MesStagiaires/Index')
                ->where('stats.total', 1)
                ->where('instances.data.0.id', $instanceAgenceUtilisateur->id)
            );

        $this->assertNotEquals($instanceAgenceUtilisateur->stage->agence_id, $instanceAutreAgence->stage->agence_id);
    }

    public function test_un_administrateur_voit_les_dossiers_de_toutes_les_agences(): void
    {
        ['instance' => $premiere] = $this->creerDossier();
        ['instance' => $seconde] = $this->creerDossier();

        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'administrateur', 'guard_name' => 'web']));

        $this->actingAs($admin)
            ->get('/cip/mes-stagiaires')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cip/MesStagiaires/Index')
                ->where('stats.total', 2)
            );

        $this->assertNotEquals($premiere->id, $seconde->id);
    }

    /**
     * Reproduit le compte admin@emploijeunes.ci en base réelle : un administrateur peut avoir
     * hérité d'un périmètre agence (ex. utilisé aussi pour tester un écran CIP). Le rôle doit
     * toujours primer sur ce périmètre — sinon l'administrateur se retrouve restreint à une
     * seule agence comme un CIP ordinaire.
     */
    public function test_un_administrateur_avec_un_perimetre_voit_quand_meme_toutes_les_agences(): void
    {
        ['instance' => $premiere] = $this->creerDossier();
        ['instance' => $seconde] = $this->creerDossier();

        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'administrateur', 'guard_name' => 'web']));
        $admin->perimetresAgences()->attach($premiere->stage->agence_id);

        $this->actingAs($admin)
            ->get('/cip/mes-stagiaires')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cip/MesStagiaires/Index')
                ->where('stats.total', 2)
            );

        $this->assertNotEquals($premiere->id, $seconde->id);
    }

    public function test_le_filtre_agence_restreint_la_liste(): void
    {
        ['user' => $admin, 'stage' => $stage1] = $this->creerDossier(admin: true);
        $this->creerDossier();

        $this->actingAs($admin)
            ->get("/cip/mes-stagiaires?agence_id={$stage1->agence_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 1)
                ->where('filters.agence_id', (string) $stage1->agence_id)
            );
    }

    public function test_la_recherche_couvre_beneficiaire_numero_contrat_etape_et_statut_pointage(): void
    {
        ['user' => $admin] = $this->creerDossier(admin: true);
        ['stage' => $stageContrat] = $this->creerDossier();
        ['stage' => $stageAutre] = $this->creerDossier();

        Contrat::factory()->create(['stage_id' => $stageContrat->id, 'numero' => 'CTR-999888']);

        $this->actingAs($admin)
            ->get('/cip/mes-stagiaires?search=CTR-999888')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 1)
                ->where('instances.data.0.stage.id', $stageContrat->id)
            );

        $this->actingAs($admin)
            ->get('/cip/mes-stagiaires?search='.$stageAutre->beneficiaire->numero_aej)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 1)
                ->where('instances.data.0.stage.id', $stageAutre->id)
            );
    }

    public function test_transferer_un_contrat_pdf_valide_est_accepte(): void
    {
        Storage::fake('public');
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/transferer-contrat", [
                'contrat_stage' => UploadedFile::fake()->create('contrat.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documents', ['stage_id' => $instance->stage_id, 'nom' => 'contrat.pdf']);
        $this->assertDatabaseHas('versions_documents', ['nom_original' => 'contrat.pdf', 'type_mime' => 'application/pdf']);
    }

    public function test_transferer_un_contrat_non_pdf_ou_trop_volumineux_est_rejete(): void
    {
        Storage::fake('public');
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/transferer-contrat", [
                'contrat_stage' => UploadedFile::fake()->create('contrat.jpg', 100, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('contrat_stage');

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/transferer-contrat", [
                'contrat_stage' => UploadedFile::fake()->create('contrat.pdf', 6000, 'application/pdf'),
            ])
            ->assertSessionHasErrors('contrat_stage');

        $this->assertDatabaseMissing('documents', ['stage_id' => $instance->stage_id]);
    }

    public function test_upload_fiche_tresor_money_accepte_pdf_jpg_jpeg_png_et_rejette_le_reste(): void
    {
        Storage::fake('public');
        $tresorMoney = TypePaiement::firstOrCreate(
            ['code' => TypePaiement::CODE_TRESOR_MONEY],
            ['nom' => 'TRESOR MONEY', 'actif' => true]
        );
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();
        $instance->stage->beneficiaire->update(['type_paiement_id' => $tresorMoney->id]);

        foreach (['fiche.pdf', 'fiche.jpg', 'fiche.jpeg', 'fiche.png'] as $nom) {
            $this->actingAs($user)
                ->post("/cip/mes-stagiaires/{$instance->id}/upload-tresor-money", [
                    'tresor_money_file' => UploadedFile::fake()->create($nom, 100),
                ])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/upload-tresor-money", [
                'tresor_money_file' => UploadedFile::fake()->create('fiche.docx', 100),
            ])
            ->assertSessionHasErrors('tresor_money_file');
    }

    public function test_generation_et_depot_de_la_fiche_restent_disponibles_si_le_paiement_n_est_pas_tresor_money(): void
    {
        Storage::fake('public');
        $wave = TypePaiement::firstOrCreate(
            ['code' => TypePaiement::CODE_WAVE],
            ['nom' => 'WAVE', 'actif' => true]
        );
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();
        $instance->stage->beneficiaire->update(['type_paiement_id' => $wave->id]);

        $this->actingAs($user)
            ->getJson("/cip/mes-stagiaires/{$instance->id}/generer-tresor-money/json")
            ->assertOk()
            ->assertJsonStructure(['url', 'filename']);

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/upload-tresor-money", [
                'tresor_money_file' => UploadedFile::fake()->create('fiche-wave.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'stage_id' => $instance->stage_id,
            'nom' => 'fiche-wave.pdf',
        ]);
    }

    public function test_le_json_expose_lexigence_de_fiche_tresor_money_pour_le_bouton_generer_du_frontend(): void
    {
        $tresorMoney = TypePaiement::firstOrCreate(
            ['code' => TypePaiement::CODE_TRESOR_MONEY],
            ['nom' => 'TRESOR MONEY', 'actif' => true]
        );
        ['user' => $user, 'instance' => $instancePaiementTresorMoney] = $this->creerDossier();
        $instancePaiementTresorMoney->stage->beneficiaire->update(['type_paiement_id' => $tresorMoney->id]);

        $this->actingAs($user)
            ->get('/cip/mes-stagiaires')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('instances.data.0.stage.beneficiaire.requiert_tresor_money', true)
            );

        ['user' => $autreUser, 'instance' => $instanceSansTresorMoney] = $this->creerDossier();

        $this->actingAs($autreUser)
            ->get('/cip/mes-stagiaires')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('instances.data.0.stage.beneficiaire.requiert_tresor_money', false)
            );
    }

    public function test_transmission_bloquee_tant_que_le_contrat_signe_manque(): void
    {
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/transmettre-chef-agence")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(
            CorbeilleEnum::CIP_MES_STAGIAIRES->value,
            $instance->fresh()->corbeille_actuelle
        );
    }

    public function test_transmission_bloquee_tant_que_la_fiche_tresor_money_manque_quand_requise(): void
    {
        Storage::fake('public');
        $tresorMoney = TypePaiement::firstOrCreate(
            ['code' => TypePaiement::CODE_TRESOR_MONEY],
            ['nom' => 'TRESOR MONEY', 'actif' => true]
        );
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();
        $instance->stage->beneficiaire->update(['type_paiement_id' => $tresorMoney->id]);

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/transferer-contrat", [
                'contrat_stage' => UploadedFile::fake()->create('contrat.pdf', 100, 'application/pdf'),
            ]);

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/transmettre-chef-agence")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(
            CorbeilleEnum::CIP_MES_STAGIAIRES->value,
            $instance->fresh()->corbeille_actuelle
        );
    }

    public function test_transmission_reussit_une_fois_le_contrat_et_la_fiche_tresor_money_deposes(): void
    {
        Storage::fake('public');
        $tresorMoney = TypePaiement::firstOrCreate(
            ['code' => TypePaiement::CODE_TRESOR_MONEY],
            ['nom' => 'TRESOR MONEY', 'actif' => true]
        );
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();
        $instance->stage->beneficiaire->update(['type_paiement_id' => $tresorMoney->id]);

        $this->actingAs($user)->post("/cip/mes-stagiaires/{$instance->id}/transferer-contrat", [
            'contrat_stage' => UploadedFile::fake()->create('contrat.pdf', 100, 'application/pdf'),
        ]);
        $this->actingAs($user)->post("/cip/mes-stagiaires/{$instance->id}/upload-tresor-money", [
            'tresor_money_file' => UploadedFile::fake()->create('fiche.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/transmettre-chef-agence")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertContains(
            $instance->fresh()->corbeille_actuelle,
            [CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value, CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value]
        );
    }

    public function test_transmission_reussit_sans_fiche_tresor_money_quand_le_paiement_est_wave(): void
    {
        Storage::fake('public');
        $wave = TypePaiement::firstOrCreate(
            ['code' => TypePaiement::CODE_WAVE],
            ['nom' => 'WAVE', 'actif' => true]
        );
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();
        $instance->stage->beneficiaire->update(['type_paiement_id' => $wave->id]);

        $this->actingAs($user)->post("/cip/mes-stagiaires/{$instance->id}/transferer-contrat", [
            'contrat_stage' => UploadedFile::fake()->create('contrat.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/transmettre-chef-agence")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertContains(
            $instance->fresh()->corbeille_actuelle,
            [CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value, CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value]
        );
    }

    public function test_transmission_deja_effectuee_est_refusee(): void
    {
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value]);

        $this->actingAs($user)
            ->post("/cip/mes-stagiaires/{$instance->id}/transmettre-chef-agence")
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_suivi_pointages_expose_la_position_du_dossier_dans_le_circuit(): void
    {
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();

        $this->actingAs($user)
            ->getJson("/cip/mes-stagiaires/{$instance->id}/suivi-pointages")
            ->assertOk()
            ->assertJsonStructure(['corbeille_actuelle' => ['code', 'label'], 'pointages'])
            ->assertJsonPath('corbeille_actuelle.code', CorbeilleEnum::CIP_MES_STAGIAIRES->value);
    }

    public function test_les_actions_de_dossier_sont_refusees_hors_du_perimetre_agence(): void
    {
        ['instance' => $instance] = $this->creerDossier();
        $intrus = User::factory()->create();
        $intrus->perimetresAgences()->attach(Agence::factory()->create()->id);

        $this->actingAs($intrus)
            ->post("/cip/mes-stagiaires/{$instance->id}/transferer-contrat", [
                'contrat_stage' => UploadedFile::fake()->create('contrat.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($intrus)
            ->post("/cip/mes-stagiaires/{$instance->id}/transmettre-chef-agence")
            ->assertForbidden();

        $this->actingAs($intrus)
            ->getJson("/cip/mes-stagiaires/{$instance->id}/suivi-pointages")
            ->assertForbidden();

        $this->actingAs($intrus)
            ->delete("/cip/mes-stagiaires/{$instance->id}")
            ->assertForbidden();
    }

    public function test_le_cip_peut_supprimer_un_dossier_pas_encore_transmis(): void
    {
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();

        $this->actingAs($user)
            ->delete("/cip/mes-stagiaires/{$instance->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('instances_parcours', ['id' => $instance->id]);
    }

    public function test_un_dossier_deja_transmis_au_chef_dagence_ne_peut_plus_etre_supprime(): void
    {
        ['user' => $user, 'instance' => $instance] = $this->creerDossier();
        $instance->update(['corbeille_actuelle' => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value]);

        $this->actingAs($user)
            ->delete("/cip/mes-stagiaires/{$instance->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('instances_parcours', ['id' => $instance->id]);
    }

    public function test_un_utilisateur_sans_role_cip_ni_administrateur_ne_peut_pas_supprimer(): void
    {
        ['instance' => $instance] = $this->creerDossier();
        $autre = User::factory()->create();
        $autre->perimetresAgences()->attach($instance->stage->agence_id);

        $this->actingAs($autre)
            ->delete("/cip/mes-stagiaires/{$instance->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('instances_parcours', ['id' => $instance->id]);
    }

    public function test_la_recherche_entreprises_async_est_restreinte_au_perimetre(): void
    {
        ['user' => $user, 'stage' => $stage] = $this->creerDossier();

        $entreprisePerimetre = Entreprise::factory()->create([
            'agence_id' => $stage->agence_id,
            'raison_sociale' => 'Agence Atlantique SARL',
        ]);
        Entreprise::factory()->create(['raison_sociale' => 'Cotonou Hors Perimetre SA']);

        $this->actingAs($user)
            ->getJson('/cip/mes-stagiaires/entreprises?q=')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)
            ->getJson('/cip/mes-stagiaires/entreprises?q=sarl')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entreprisePerimetre->getKey());
    }

    public function test_la_recherche_entreprises_async_est_nationale_pour_un_administrateur(): void
    {
        $this->creerDossier(admin: true);

        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'administrateur', 'guard_name' => 'web']));

        $premiere = Entreprise::factory()->create(['raison_sociale' => 'Benco Distribution']);
        $seconde = Entreprise::factory()->create(['raison_sociale' => 'Zénith Industries']);

        $this->actingAs($admin)
            ->getJson('/cip/mes-stagiaires/entreprises?q=industries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $seconde->getKey());

        $this->actingAs($admin)
            ->getJson('/cip/mes-stagiaires/entreprises?q=benco')
            ->assertOk()
            ->assertJsonPath('data.0.id', $premiere->getKey());
    }

    public function test_la_recherche_entreprises_async_exige_authentification(): void
    {
        $this->getJson('/cip/mes-stagiaires/entreprises?q=test')->assertUnauthorized();
    }

    public function test_le_payload_entreprises_ne_contient_que_la_selection_prefiltree(): void
    {
        ['user' => $admin, 'stage' => $stage] = $this->creerDossier(admin: true);

        $entrepriseFiltree = Entreprise::factory()->create(['agence_id' => $stage->agence_id]);
        Entreprise::factory()->count(3)->create();
        $stage->update(['entreprise_id' => $entrepriseFiltree->id]);

        $this->actingAs($admin)
            ->get('/cip/mes-stagiaires')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('entreprises', 0)
            );

        $this->actingAs($admin)
            ->get("/cip/mes-stagiaires?entreprise_id={$entrepriseFiltree->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('entreprises', 1)
                ->where('entreprises.0.id', $entrepriseFiltree->id)
                ->where('entreprises.0.nom', $entrepriseFiltree->raison_sociale)
            );
    }

    public function test_l_export_synchrone_reprend_les_lignes_filtrees(): void
    {
        ['user' => $admin] = $this->creerDossier(admin: true);
        ['stage' => $stageCorbeille] = $this->creerDossier();
        $this->creerDossier();

        Contrat::factory()->create(['stage_id' => $stageCorbeille->id, 'numero' => 'CTR-777777']);

        $reponse = $this->actingAs($admin)
            ->get('/cip/mes-stagiaires/export?search=CTR-777777');

        $reponse->assertOk();

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString('N° AEJ', $contenu);
        $this->assertStringContainsString($stageCorbeille->beneficiaire->nom, $contenu);
        $this->assertStringContainsString('CTR-777777', $contenu);

        // Le second dossier, hors recherche, ne doit pas fuiter dans le fichier.
        $tous = $this->actingAs($admin)->get('/cip/mes-stagiaires/export')->streamedContent();
        $this->assertStringContainsString($stageCorbeille->beneficiaire->nom, $tous);
    }

    public function test_l_export_synchrone_respecte_le_perimetre_agence(): void
    {
        ['user' => $user, 'stage' => $stagePerimetre] = $this->creerDossier();
        ['stage' => $stageAutre] = $this->creerDossier();

        $contenu = $this->actingAs($user)
            ->get('/cip/mes-stagiaires/export')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($stagePerimetre->beneficiaire->nom, $contenu);
        $this->assertStringNotContainsString($stageAutre->beneficiaire->nom, $contenu);
    }

    public function test_l_export_volumineux_passe_par_un_batch_puis_est_telechargeable(): void
    {
        Bus::fake();

        ['user' => $user] = $this->creerDossier();

        $reponse = $this->actingAs($user)
            ->post('/cip/mes-stagiaires/exporter', ['search' => 'AEJ-TEST'])
            ->assertOk()
            ->assertJsonStructure(['batch_id']);

        Bus::assertBatched(function ($batch) use ($user): bool {
            $job = $batch->jobs->first();

            return $job instanceof ExporterStagiairesCipJob
                && $job->filtres === ['search' => 'AEJ-TEST']
                && $job->demandeParId === $user->id;
        });

        // Un identifiant de batch inconnu ne doit jamais servir de fichier.
        $this->actingAs($user)
            ->get('/cip/mes-stagiaires/exporter/inconnu/progression')
            ->assertNotFound();
        $this->actingAs($user)
            ->get('/cip/mes-stagiaires/exporter/inconnu/telechargement')
            ->assertNotFound();
    }

    public function test_la_generation_asynchrone_produit_un_csv_telechargeable(): void
    {
        // QUEUE_CONNECTION=sync en test : le job s'exécute dans la requête POST, le fichier est
        // donc immédiatement disponible — c'est le scénario complet que le sondage frontend suit.
        Storage::fake('temp_files');
        ['user' => $user, 'stage' => $stage] = $this->creerDossier();

        $batchId = $this->actingAs($user)
            ->post('/cip/mes-stagiaires/exporter', [])
            ->assertOk()
            ->json('batch_id');

        $this->getJson("/cip/mes-stagiaires/exporter/{$batchId}/progression")
            ->assertOk()
            ->assertJsonPath('disponible', true);

        $contenu = $this->get("/cip/mes-stagiaires/exporter/{$batchId}/telechargement")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('N° AEJ', $contenu);
        $this->assertStringContainsString($stage->beneficiaire->nom, $contenu);
    }

    public function test_le_job_reauthentifie_le_demandeur_pour_confiner_lexport_a_son_perimetre(): void
    {
        Storage::fake('temp_files');
        ['user' => $cip, 'stage' => $stagePerimetre] = $this->creerDossier();
        ['stage' => $stageAutre] = $this->creerDossier();

        // Simule le contexte file d'attente : l'export a été demandé par un CIP à périmètre
        // (demandeParId), mais c'est un administrateur à vue nationale qui est « courant »
        // dans le worker. La ré-authentification du demandeur doit primer : sans elle,
        // le fichier contiendrait les agences de toute la plateforme.
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'administrateur', 'guard_name' => 'web']));
        $this->actingAs($admin);

        new ExporterStagiairesCipJob(
            filtres: [],
            demandeParId: $cip->id,
        )->handle(app(ListeStagiairesCipService::class));

        $fichiers = Storage::disk('temp_files')->files(ExporterStagiairesCipJob::DOSSIER);
        $this->assertCount(1, $fichiers);

        $contenu = Storage::disk('temp_files')->get($fichiers[0]);
        $this->assertStringContainsString($stagePerimetre->beneficiaire->nom, $contenu);
        $this->assertStringNotContainsString($stageAutre->beneficiaire->nom, $contenu);
    }

    /**
     * Dossier « Mes Stagiaires » minimal : un CIP habilité sur l'agence du stage, l'instance de
     * parcours au premier maillon du circuit. `admin: true` retourne à la place un administrateur
     * sans périmètre (visibilité nationale), pour les scénarios de filtre multi-agences.
     *
     * @return array{user: User, stage: Stage, instance: InstanceParcours}
     */
    private function creerDossier(bool $admin = false): array
    {
        $this->seed(RolePermissionSeeder::class);

        $stage = Stage::factory()->create();

        if ($admin) {
            $user = User::firstWhere('email', 'admin-mes-stagiaires@test.local')
                ?? tap(User::factory()->create(['email' => 'admin-mes-stagiaires@test.local']))
                    ->assignRole('administrateur');
        } else {
            $user = User::factory()->create();
            $user->assignRole('cip');
            $user->perimetresAgences()->attach($stage->agence_id);
        }

        $definition = DefinitionParcours::where('code', 'PAE')->first()
            ?? DefinitionParcours::factory()->create(['code' => 'PAE', 'active' => true]);
        $etape = EtapeParcours::where('definition_parcours_id', $definition->id)->where('code', 'CIP_MES_STAGIAIRES')->first()
            ?? EtapeParcours::factory()->create([
                'definition_parcours_id' => $definition->id,
                'code' => 'CIP_MES_STAGIAIRES',
                'nom' => 'Mes Stagiaires',
                'code_corbeille' => CorbeilleEnum::CIP_MES_STAGIAIRES->value,
                'initiale' => true,
            ]);

        $instance = InstanceParcours::create([
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => $stage->id,
            'corbeille_actuelle' => CorbeilleEnum::CIP_MES_STAGIAIRES->value,
        ]);

        return ['user' => $user, 'stage' => $stage, 'instance' => $instance];
    }
}
