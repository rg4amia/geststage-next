<?php

namespace App\Domain\Payment\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\Payment\BordereauPaiement;
use Illuminate\Support\Facades\DB;

class AgentComptableService
{
    public function __construct(private WorkflowTransitionService $workflowService) {}

    /**
     * Vise un bordereau de paiement transmis par la DMG.
     */
    public function viserBordereau(BordereauPaiement $bordereau): void
    {
        DB::transaction(function () use ($bordereau) {
            $bordereau->update(['statut' => 'VISE_AC']);

            // Cascade vers OPs
            foreach ($bordereau->ordresPaiement as $op) {
                $op->update(['statut' => 'VISE_AC']);

                // Cascade vers Dossiers
                foreach ($op->dossiersPaiement as $dossier) {
                    $dossier->update(['statut' => 'VISE_AC']);

                    // Cascade vers Paiements
                    foreach ($dossier->paiements as $paiement) {
                        $paiement->update(['statut' => 'VALIDE_AC']);
                    }
                }
            }

            $this->workflowService->acViseBordereau($bordereau);
        });
    }

    /**
     * Diffère un bordereau de paiement, le renvoyant à la DMG pour correction, avec un motif.
     */
    public function ajournerBordereau(BordereauPaiement $bordereau, string $motif): void
    {
        DB::transaction(function () use ($bordereau, $motif) {
            $bordereau->update(['statut' => 'REJETE_AC']);

            // Cascade d'ajournement
            foreach ($bordereau->ordresPaiement as $op) {
                $op->update(['statut' => 'REJETE_AC']);

                foreach ($op->dossiersPaiement as $dossier) {
                    $dossier->update(['statut' => 'AJOURNE_DMG']);

                    foreach ($dossier->paiements as $paiement) {
                        $paiement->update(['statut' => 'A_TRAITER']);
                        $dossier->paiements()->updateExistingPivot($paiement->id, ['motif_retrait' => $motif]);
                    }
                }
            }

            $this->workflowService->acDiffereBordereau($bordereau);
        });
    }

    /**
     * Rejette définitivement un bordereau de paiement (contrairement à l'ajournement, aucun retour possible sans intervention manuelle).
     */
    public function rejeterBordereau(BordereauPaiement $bordereau, string $motif): void
    {
        DB::transaction(function () use ($bordereau, $motif) {
            $bordereau->update(['statut' => 'REJETE_AC_DEFINITIF']);

            foreach ($bordereau->ordresPaiement as $op) {
                $op->update(['statut' => 'REJETE_AC_DEFINITIF']);

                foreach ($op->dossiersPaiement as $dossier) {
                    foreach ($dossier->paiements as $paiement) {
                        $dossier->paiements()->updateExistingPivot($paiement->id, ['motif_retrait' => $motif]);
                    }
                }
            }

            $this->workflowService->acRejetteBordereau($bordereau);
        });
    }
}
