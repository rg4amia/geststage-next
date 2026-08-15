<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_roles_and_permissions_are_seeded(): void
    {
        $this->assertTrue(Role::where('name', 'administrateur')->exists());
        $this->assertTrue(Role::where('name', 'cip')->exists());
        $this->assertTrue(Permission::where('name', 'voir_utilisateurs')->exists());
        $this->assertTrue(Permission::where('name', 'valider_chef_agence')->exists());
    }

    public function test_cip_has_correct_permissions(): void
    {
        $cipRole = Role::findByName('cip');
        
        $this->assertTrue($cipRole->hasPermissionTo('voir_beneficiaires'));
        $this->assertFalse($cipRole->hasPermissionTo('valider_chef_agence'));
    }

    public function test_administrateur_bypasses_all_permissions_via_gate(): void
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('administrateur');

        // L'admin ne possède pas directement la permission dans le rôle...
        $adminRole = Role::findByName('administrateur');
        $this->assertFalse($adminRole->hasPermissionTo('voir_beneficiaires'));

        // ...mais grâce au Gate::before, il peut "faire" cette action
        $this->assertTrue($adminUser->can('voir_beneficiaires'));
        $this->assertTrue($adminUser->can('une_permission_qui_n_existe_meme_pas'));
    }

    public function test_user_policy_uses_permissions(): void
    {
        $cipUser = User::factory()->create();
        $cipUser->assignRole('cip');

        // Un CIP ne peut pas voir les utilisateurs (il n'a pas la permission 'voir_utilisateurs')
        $this->assertFalse($cipUser->can('viewAny', User::class));

        $adminUser = User::factory()->create();
        $adminUser->assignRole('administrateur');
        
        // L'admin peut voir les utilisateurs
        $this->assertTrue($adminUser->can('viewAny', User::class));
    }
}
