<?php

namespace Tests\Feature\Registration;

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
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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

        $roleCIP = Role::firstOrCreate(['name' => 'CIP']);
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
        $chefAgence->assignRole(Role::firstOrCreate(['name' => 'chef_agence']));
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
        $chefAgence->assignRole(Role::firstOrCreate(['name' => 'chef_agence']));
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
        $chefAgence->assignRole(Role::firstOrCreate(['name' => 'chef_agence']));
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
        $chefAgence->assignRole(Role::firstOrCreate(['name' => 'chef_agence']));
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
