<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow\InstanceParcours;

class InstanceParcoursPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Simplification pour le moment
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InstanceParcours $instanceParcours): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models (inscription).
     */
    public function create(User $user): bool
    {
        return $user->hasRole('CIP'); // Seul le CIP peut inscrire
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InstanceParcours $instanceParcours): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InstanceParcours $instanceParcours): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InstanceParcours $instanceParcours): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InstanceParcours $instanceParcours): bool
    {
        return false;
    }
}
