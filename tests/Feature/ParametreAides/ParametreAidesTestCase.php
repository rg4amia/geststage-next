<?php

namespace Tests\Feature\ParametreAides;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ParametreAidesTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * L'administrateur bypasse toutes les permissions via `Gate::before`
     * (voir `AppServiceProvider::boot()`), y compris `gerer_agences`, que la
     * seed ne distribue volontairement à aucun rôle métier.
     */
    protected function creerAdministrateur(): User
    {
        $administrateur = User::factory()->create();
        $administrateur->assignRole('administrateur');

        return $administrateur;
    }
}
