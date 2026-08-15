<?php

namespace Tests\Feature\Registration;

use App\Models\Company\Entreprise;
use App\Models\Company\OffreEmploi;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ]
        ];

        $response = $this->actingAs($this->cip)->post('/inscriptions', $payload);

        $response->assertRedirect('/inscriptions');
        $this->assertDatabaseHas('beneficiaires', ['nom' => 'Doe']);
    }
}
