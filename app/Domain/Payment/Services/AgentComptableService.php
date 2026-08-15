<?php

namespace App\Domain\Payment\Services;

use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use Illuminate\Support\Facades\DB;

class AgentComptableService
{
    /**
     * Vise un dossier de paiement transmis par la DMG.
     */
    public function viserDossier(DossierPaiement $dossier): void
    {
        DB::transaction(function () use ($dossier) {
            $dossier->update(['statut' => 'VISE_AC']);

            // Dans un flux réel, on pourrait ensuite l'envoyer au CB ou au Trésor.
            // Pour l'instant on le considère validé à cette étape.
            
            // Mettre à jour les paiements sous-jacents
            foreach ($dossier->paiements as $paiement) {
                $paiement->update(['statut' => 'VALIDE_AC']);
            }
        });
    }

    /**
     * Rejette un dossier de paiement, le renvoyant à la DMG avec un motif.
     */
    public function ajournerDossier(DossierPaiement $dossier, string $motif): void
    {
        DB::transaction(function () use ($dossier, $motif) {
            $dossier->update(['statut' => 'AJOURNE_DMG']);

            // Dé-lier les paiements pour qu'ils retournent à l'état initial ?
            // Ou garder la trace. Dans Gestage on garde le motif.
            foreach ($dossier->paiements as $paiement) {
                $paiement->update(['statut' => 'A_TRAITER']); // Repart à la case départ
                // Enregistrer le motif d'ajournement quelque part (table de logs ou pivot)
                $dossier->paiements()->updateExistingPivot($paiement->id, ['motif_retrait' => $motif]);
            }
        });
    }
}
