<?php

namespace App\Policies;

use App\Models\Company\OffreEmploi;
use App\Models\User;

class OffreEmploiPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('voir_offres');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OffreEmploi $offreEmploi): bool
    {
        return $user->hasPermissionTo('voir_offres');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gerer_offres');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OffreEmploi $offreEmploi): bool
    {
        return $user->hasPermissionTo('gerer_offres');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OffreEmploi $offreEmploi): bool
    {
        return $user->hasPermissionTo('gerer_offres');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, OffreEmploi $offreEmploi): bool
    {
        return $user->hasPermissionTo('gerer_offres');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, OffreEmploi $offreEmploi): bool
    {
        return $user->hasPermissionTo('gerer_offres');
    }
}
