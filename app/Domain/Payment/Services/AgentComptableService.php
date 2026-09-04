<?php

namespace App\Domain\Payment\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DecisionPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentComptableService
{
    public function __construct(private WorkflowTransitionService $workflowService) {}

    public function viserBordereau(BordereauPaiement $bordereau): void
    {
        DB::transaction(function () use ($bordereau): void {
            $this->preparerTraitement($bordereau, 'VISE_AC');

            foreach ($bordereau->ordresPaiement as $ordre) {
                $ordre->update(['statut' => 'VISE_AC']);

                foreach ($ordre->dossiersPaiement as $dossier) {
                    $dossier->update(['statut' => 'VISE_AC']);
                    $dossier->paiementsActifs()->update(['statut' => 'VALIDE_AC']);
                }
            }

            $this->workflowService->acViseBordereau($bordereau);
        });
    }

    public function validerOrdre(OrdrePaiement $ordre, User $auteur): void
    {
        DB::transaction(function () use ($ordre, $auteur): void {
            $bordereau = $this->preparerOrdre($ordre, 'VISE_AC');

            foreach ($ordre->dossiersPaiement as $dossier) {
                $dossier->update(['statut' => 'VISE_AC']);
                foreach ($dossier->paiementsActifs as $paiement) {
                    $statutAvant = $paiement->statut;
                    $paiement->update(['statut' => 'VALIDE_AC', 'corbeille_actuelle' => null]);
                    DecisionPaiement::enregistrer($paiement, $auteur, 'VALIDATION_OP_AC', null, $statutAvant, 'VALIDE_AC');
                }
            }

            $this->finaliserBordereau($bordereau);
        });
    }

    public function differerOrdre(OrdrePaiement $ordre, User $auteur, string $motif): void
    {
        DB::transaction(function () use ($ordre, $auteur, $motif): void {
            $bordereau = $this->preparerOrdre($ordre, 'DIFFERE_AC');

            foreach ($ordre->dossiersPaiement as $dossier) {
                $dossier->update(['statut' => 'AJOURNE_DMG']);
                foreach ($dossier->paiementsActifs as $paiement) {
                    $statutAvant = $paiement->statut;
                    $paiement->update([
                        'statut' => 'A_TRAITER',
                        'corbeille_actuelle' => CorbeilleEnum::DMG_OP_DIFFERE_AC,
                    ]);
                    $dossier->paiements()->updateExistingPivot($paiement->id, ['motif_retrait' => $motif]);
                    DecisionPaiement::enregistrer($paiement, $auteur, 'DIFFERE_OP_AC', $motif, $statutAvant, 'A_TRAITER');
                }
            }

            $this->finaliserBordereau($bordereau);
        });
    }

    public function rejeterOrdre(OrdrePaiement $ordre, User $auteur, string $motif): void
    {
        DB::transaction(function () use ($ordre, $auteur, $motif): void {
            $bordereau = $this->preparerOrdre($ordre, 'REJETE_AC');

            foreach ($ordre->dossiersPaiement as $dossier) {
                $dossier->update(['statut' => 'REJETE_AC']);
                foreach ($dossier->paiementsActifs as $paiement) {
                    $statutAvant = $paiement->statut;
                    $paiement->update([
                        'statut' => 'REJETE_AC',
                        'corbeille_actuelle' => CorbeilleEnum::DMG_OP_REJETE_AC,
                    ]);
                    $dossier->paiements()->updateExistingPivot($paiement->id, ['motif_retrait' => $motif]);
                    DecisionPaiement::enregistrer($paiement, $auteur, 'REJET_OP_AC', $motif, $statutAvant, 'REJETE_AC');
                }
            }

            $this->finaliserBordereau($bordereau);
        });
    }

    public function retirerOrdre(OrdrePaiement $ordre, User $auteur, string $motif): void
    {
        DB::transaction(function () use ($ordre, $auteur, $motif): void {
            $bordereau = $this->preparerOrdre($ordre, 'BROUILLON');

            $ordre->update(['bordereau_paiement_id' => null]);
            foreach ($ordre->dossiersPaiement as $dossier) {
                $dossier->update(['statut' => 'VALIDE_CB']);
                foreach ($dossier->paiementsActifs as $paiement) {
                    $statutAvant = $paiement->statut;
                    $paiement->update([
                        'statut' => 'EN_OP',
                        'corbeille_actuelle' => CorbeilleEnum::DMG_OP_ATTENTE_BORDEREAU,
                    ]);
                    DecisionPaiement::enregistrer($paiement, $auteur, 'RETRAIT_OP_BORDEREAU_AC', $motif, $statutAvant, 'EN_OP');
                }
            }

            $bordereau->update([
                'montant_total' => $bordereau->ordresPaiement()->sum('montant_total'),
            ]);
            $this->finaliserBordereau($bordereau);
        });
    }

    public function ajournerBordereau(BordereauPaiement $bordereau, string $motif): void
    {
        DB::transaction(function () use ($bordereau, $motif): void {
            $this->preparerTraitement($bordereau, 'REJETE_AC');

            foreach ($bordereau->ordresPaiement as $ordre) {
                $ordre->update(['statut' => 'REJETE_AC']);

                foreach ($ordre->dossiersPaiement as $dossier) {
                    $dossier->update(['statut' => 'AJOURNE_DMG']);
                    $dossier->paiementsActifs()->update(['statut' => 'A_TRAITER']);
                    $dossier->paiements()->updateExistingPivot(
                        $dossier->paiementsActifs->modelKeys(),
                        ['motif_retrait' => $motif],
                    );
                }
            }

            $this->workflowService->acDiffereBordereau($bordereau);
        });
    }

    public function rejeterBordereau(BordereauPaiement $bordereau, string $motif): void
    {
        DB::transaction(function () use ($bordereau, $motif): void {
            $this->preparerTraitement($bordereau, 'REJETE_AC_DEFINITIF');

            foreach ($bordereau->ordresPaiement as $ordre) {
                $ordre->update(['statut' => 'REJETE_AC_DEFINITIF']);

                foreach ($ordre->dossiersPaiement as $dossier) {
                    $dossier->update(['statut' => 'REJETE_AC_DEFINITIF']);
                    $dossier->paiements()->updateExistingPivot(
                        $dossier->paiementsActifs->modelKeys(),
                        ['motif_retrait' => $motif],
                    );
                }
            }

            $this->workflowService->acRejetteBordereau($bordereau);
        });
    }

    private function preparerTraitement(BordereauPaiement $bordereau, string $nouveauStatut): void
    {
        $bordereau->loadMissing('ordresPaiement.dossiersPaiement.paiementsActifs');

        $nombrePaiements = $bordereau->ordresPaiement
            ->sum(fn ($ordre): int => $ordre->dossiersPaiement->sum(
                fn ($dossier): int => $dossier->paiementsActifs->count(),
            ));

        if ($nombrePaiements === 0) {
            throw ValidationException::withMessages([
                'bordereau' => 'Ce bordereau ne contient aucun paiement à traiter.',
            ]);
        }

        $updated = BordereauPaiement::query()
            ->whereKey($bordereau->getKey())
            ->where('statut', 'TRANSMIS_AC')
            ->update(['statut' => $nouveauStatut]);

        if ($updated !== 1) {
            throw ValidationException::withMessages([
                'bordereau' => 'Ce bordereau a déjà été traité par un autre utilisateur.',
            ]);
        }

        $bordereau->statut = $nouveauStatut;
    }

    private function preparerOrdre(OrdrePaiement $ordre, string $nouveauStatut): BordereauPaiement
    {
        $ordre->loadMissing(['bordereau', 'dossiersPaiement.paiementsActifs']);
        $bordereau = $ordre->bordereau;

        if (! $bordereau || $bordereau->statut !== 'TRANSMIS_AC') {
            throw ValidationException::withMessages([
                'ordre' => 'Cette OP ne dépend plus d’un bordereau en cours de traitement AC.',
            ]);
        }

        if ($ordre->dossiersPaiement->sum(fn ($dossier): int => $dossier->paiementsActifs->count()) === 0) {
            throw ValidationException::withMessages([
                'ordre' => 'Cette OP ne contient aucun paiement actif à traiter.',
            ]);
        }

        $updated = OrdrePaiement::query()
            ->whereKey($ordre->getKey())
            ->where('bordereau_paiement_id', $bordereau->id)
            ->where('statut', 'EN_BORDEREAU')
            ->update(['statut' => $nouveauStatut]);

        if ($updated !== 1) {
            throw ValidationException::withMessages([
                'ordre' => 'Cette OP a déjà été traitée par un autre utilisateur.',
            ]);
        }

        $ordre->statut = $nouveauStatut;

        return $bordereau;
    }

    private function finaliserBordereau(BordereauPaiement $bordereau): void
    {
        $statuts = $bordereau->ordresPaiement()->pluck('statut');

        if ($statuts->isEmpty()) {
            $bordereau->update(['statut' => 'ANNULE', 'montant_total' => 0]);

            return;
        }

        if ($statuts->contains('EN_BORDEREAU')) {
            return;
        }

        $bordereau->update([
            'statut' => $statuts->every(fn (string $statut): bool => $statut === 'VISE_AC')
                ? 'VISE_AC'
                : 'REJETE_AC',
        ]);
    }
}
