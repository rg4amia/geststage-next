<?php

namespace Tests\Feature\Company;

use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntrepriseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles/permissions are set up
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_cip_can_view_entreprises_index()
    {
        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $response = $this->actingAs($cip)->get('/entreprises');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Entreprises/Index'));
    }

    public function test_unauthorized_user_cannot_view_entreprises()
    {
        $user = User::factory()->create(); // No role

        $response = $this->actingAs($user)->get('/entreprises');

        $response->assertStatus(403);
    }

    public function test_cip_can_create_entreprise()
    {
        $cip = User::factory()->create();
        $cip->assignRole('cip');

        $agence = Agence::factory()->create();

        $response = $this->actingAs($cip)->post('/entreprises', [
            'agence_id' => $agence->id,
            'raison_sociale' => 'Nouvelle Entreprise SA',
            'actif' => true,
        ]);

        $response->assertRedirect('/entreprises');
        $this->assertDatabaseHas('entreprises', [
            'raison_sociale' => 'Nouvelle Entreprise SA',
        ]);
    }
}
