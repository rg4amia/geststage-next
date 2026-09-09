<?php

namespace Tests\Feature\Dmg;

use App\Models\Company\Entreprise;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recherche async d'entreprises pour le filtre react-select de /dmg/paiements :
 * casse, garde-fou de saisie, plafond de résultats et permissions.
 */
class EntrepriseRechercheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le cache de référence survit au RefreshDatabase (store array du processus PHPUnit) :
        // on repart d'un état propre pour que les fabriques soient visibles de la recherche.
        Entreprise::forgetCached();
        $this->seed(RolePermissionSeeder::class);
    }

    private function acteur(string ...$roles): User
    {
        $user = User::factory()->create();

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        $this->actingAs($user);

        return $user;
    }

    public function test_recherche_insidensible_a_la_casse(): void
    {
        $this->acteur('administrateur');
        Entreprise::factory()->create(['raison_sociale' => 'Cotonou Industries']);
        Entreprise::factory()->create(['raison_sociale' => 'Atlantique SARL']);

        $reponse = $this->getJson('/dmg/paiements/entreprises?q=coton');

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.raison_sociale', 'Cotonou Industries');
    }

    public function test_moins_de_deux_caracteres_ne_renvoie_rien(): void
    {
        $this->acteur('administrateur');
        Entreprise::factory()->count(3)->create();

        $this->getJson('/dmg/paiements/entreprises?q=a')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/dmg/paiements/entreprises')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_resultats_plafnes_a_cinquante(): void
    {
        $this->acteur('administrateur');
        Entreprise::factory()->count(60)->create(['raison_sociale' => 'Zeta Corp']);

        $this->getJson('/dmg/paiements/entreprises?q=zeta')
            ->assertOk()
            ->assertJsonCount(50, 'data');
    }

    public function test_acces_refuse_sans_permission(): void
    {
        $this->acteur('chef_agence');

        $this->getJson('/dmg/paiements/entreprises?q=test')->assertForbidden();
    }

    public function test_acces_exige_authentification(): void
    {
        $this->getJson('/dmg/paiements/entreprises?q=test')->assertUnauthorized();
    }
}
