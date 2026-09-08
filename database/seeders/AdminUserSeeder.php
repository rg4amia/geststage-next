<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Garantit l'existence du compte administrateur avec son rôle.
 *
 * Sans ce seeder, un compte admin créé à la main (ou via migration de données legacy)
 * peut se retrouver sans rôle : la Gate::before n'accorde alors pas le bypass global
 * et les contrôles de périmètre agence le rejettent en 403.
 */
class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@emploijeunes.ci'],
            [
                'nom' => 'Administrateur',
                'password' => env('ADMIN_USER_PASSWORD', 'password'),
                'actif' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('administrateur')) {
            $admin->assignRole('administrateur');
        }
    }
}
