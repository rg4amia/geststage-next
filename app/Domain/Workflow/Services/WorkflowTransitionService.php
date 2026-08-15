<?php

namespace App\Domain\Workflow\Services;

use App\Models\User;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\EvenementParcours;
use App\Models\Workflow\InstanceParcours;
use App\Models\Workflow\TacheParcours;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class WorkflowTransitionService
{
    /**
     * Transitionne une instance de parcours vers une nouvelle étape.
     *
     * @param InstanceParcours $instance
     * @param EtapeParcours $nouvelleEtape
     * @param User $acteur
     * @param array $donneesContexte
     * @return TacheParcours La nouvelle tâche créée
     * @throws LogicException Si aucune tâche n'est ouverte pour cette instance
     */
    public function transitionner(
        InstanceParcours $instance,
        EtapeParcours $nouvelleEtape,
        User $acteur,
        array $donneesContexte = []
    ): TacheParcours {
        return DB::transaction(function () use ($instance, $nouvelleEtape, $acteur, $donneesContexte) {
            // 1. Verrouiller l'instance pour éviter la concurrence
            $instance = InstanceParcours::where('id', $instance->id)->lockForUpdate()->firstOrFail();

            // 2. Trouver la tâche actuellement ouverte ou revendiquée
            $tacheActuelle = TacheParcours::where('instance_parcours_id', $instance->id)
                ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])
                ->lockForUpdate()
                ->first();

            if (! $tacheActuelle) {
                throw new LogicException("Aucune tâche ouverte ou revendiquée pour l'instance {$instance->id}.");
            }

            // 3. Fermer la tâche actuelle
            $tacheActuelle->update([
                'statut' => 'TERMINEE',
                'fermee_le' => now(),
            ]);

            // 4. Mettre à jour l'instance avec la nouvelle étape
            $instance->update([
                'etape_courante_id' => $nouvelleEtape->id,
            ]);

            // 5. Créer la nouvelle tâche
            $nouvelleTache = TacheParcours::create([
                'instance_parcours_id' => $instance->id,
                'etape_parcours_id' => $nouvelleEtape->id,
                'role_responsable_id' => $nouvelleEtape->role_responsable_id,
                'code_corbeille' => $nouvelleEtape->code_corbeille ?? 'DEFAULT',
                'statut' => 'OUVERTE',
                'ouverte_le' => now(),
            ]);

            // 6. Enregistrer l'événement immuable
            EvenementParcours::create([
                'instance_parcours_id' => $instance->id,
                'etape_source_id' => $tacheActuelle->etape_parcours_id,
                'etape_cible_id' => $nouvelleEtape->id,
                'auteur_id' => $acteur->id,
                'type' => 'TRANSITION',
                'cle_idempotence' => $tacheActuelle->id . '-' . $nouvelleEtape->id . '-' . time(),
                'donnees' => $donneesContexte,
                'survenu_le' => now(),
            ]);

            return $nouvelleTache;
        });
    }
}
