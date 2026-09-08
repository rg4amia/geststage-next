<?php

namespace App\Policies;

use App\Enums\CorbeilleEnum;
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
        return $user->hasRole('cip'); // Seul le CIP peut inscrire
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
     *
     * Un dossier ne peut être supprimé que par le CIP (ou un administrateur) et tant qu'il n'a
     * pas encore été transmis au Chef d'Agence : passé ce stade, il est engagé dans le circuit
     * de validation et sa suppression casserait l'historique du workflow.
     */
    public function delete(User $user, InstanceParcours $instanceParcours): bool
    {
        if (! ($user->hasRole('administrateur') || $user->hasRole('cip'))) {
            return false;
        }

        return in_array($instanceParcours->corbeille_actuelle, CorbeilleEnum::nonTransmisesChefAgence(), true);
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
