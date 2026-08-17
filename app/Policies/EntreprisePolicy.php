<?php

namespace App\Policies;

use App\Models\Company\Entreprise;
use App\Models\User;

class EntreprisePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('voir_entreprises');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Entreprise $entreprise): bool
    {
        // En plus de la permission, on pourrait vérifier si l'entreprise appartient à l'agence de l'utilisateur
        // Mais pour l'instant, on se base sur la permission globale (Administrateur a un bypass)
        return $user->hasPermissionTo('voir_entreprises');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gerer_entreprises');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Entreprise $entreprise): bool
    {
        return $user->hasPermissionTo('gerer_entreprises');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Entreprise $entreprise): bool
    {
        return $user->hasPermissionTo('gerer_entreprises');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Entreprise $entreprise): bool
    {
        return $user->hasPermissionTo('gerer_entreprises');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Entreprise $entreprise): bool
    {
        return $user->hasPermissionTo('gerer_entreprises');
    }
}
