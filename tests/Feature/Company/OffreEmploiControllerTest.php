<?php

namespace Tests\Feature\Company;

use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OffreEmploiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cip_can_view_offres_index()
    {
        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $response = $this->actingAs($cip)->get('/offres');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Offres/Index'));
    }

    public function test_cip_can_create_offre()
    {
        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $entreprise = Entreprise::factory()->create();
        $agence = Agence::factory()->create();
        $typeStage = TypeStage::factory()->create();
        $source = SourceFinancement::factory()->create();

        $response = $this->actingAs($cip)->post('/offres', [
            'entreprise_id' => $entreprise->id,
            'agence_id' => $agence->id,
            'type_stage_id' => $typeStage->id,
            'source_financement_id' => $source->id,
            'numero' => 'OFFRE-TEST-001',
            'intitule' => 'Développeur Fullstack',
            'nombre_places' => 2,
            'statut' => 'BROUILLON',
        ]);

        $response->assertRedirect('/offres');
        $this->assertDatabaseHas('offres_emploi', [
            'numero' => 'OFFRE-TEST-001',
            'intitule' => 'Développeur Fullstack',
        ]);
    }

    public function test_cip_can_load_an_aej_offer_by_reference(): void
    {
        Http::fake([
            'https://agenceemploijeunes.ci/*' => Http::response([
                'data' => [
                    'noreference' => 'AEJ-001',
                    'intitule' => 'Assistant développeur',
                    'nombreposte' => 4,
                    'typestage' => 'STAGE ECOLE',
                    'nomentreprise' => 'Entreprise test',
                    'datepublication' => '2026-09-01',
                    'dateexpiration' => '2026-10-01',
                ],
            ]),
        ]);
        config()->set('services.agence_emploi_jeunes.offers_token', 'test-token');

        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $this->actingAs($cip)
            ->getJson('/offres/reference/AEJ-001')
            ->assertOk()
            ->assertJsonPath('data.reference', 'AEJ-001')
            ->assertJsonPath('data.nombre_places', 4)
            ->assertJsonPath('data.publiee_le', '2026-09-01');
    }
}
