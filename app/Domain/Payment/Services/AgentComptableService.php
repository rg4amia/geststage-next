<?php

namespace App\Domain\Payment\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DecisionPaiement;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentComptableService
{
    /**
     * Statut d'un paiement encore en attente du visa de l'Agent Comptable. Équivalent du
     * `paiement_models.status_ac = 'processed'` du legacy : toutes les décisions de l'AC
     * (visa, différé, rejet) ne portent que sur ces paiements-là, jamais sur ceux qui ont
     * déjà été tranchés.
     */
    public const STATUT_ATTENTE_AC = 'EN_OP';

    public function __construct(private WorkflowTransitionService $workflowService) {}

    public function viserBordereau(BordereauPaiement $bordereau): void
    {
        DB::transaction(function () use ($bordereau): void {
            $this->preparerTraitement($bordereau, 'VISE_AC');

            foreach ($bordereau->ordresPaiement as $ordre) {
                if ($ordre->statut !== 'EN_BORDEREAU') {
                    continue;
                }

                $ordre->update(['statut' => 'VISE_AC']);

                foreach ($ordre->dossiersPaiement as $dossier) {
                    $enAttente = $dossier->paiementsActifs->where('statut', self::STATUT_ATTENTE_AC);

                    if ($enAttente->isEmpty()) {
                        continue;
                    }

                    $dossier->update(['statut' => 'VISE_AC']);
                    $dossier->paiementsActifs()
                        ->where('paiements.statut', self::STATUT_ATTENTE_AC)
                        ->update(['statut' => 'VALIDE_AC']);
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
                $enAttente = $dossier->paiementsActifs->where('statut', self::STATUT_ATTENTE_AC);

                if ($enAttente->isEmpty()) {
                    continue;
                }

                $dossier->update(['statut' => 'VISE_AC']);
                foreach ($enAttente as $paiement) {
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
                $enAttente = $dossier->paiementsActifs->where('statut', self::STATUT_ATTENTE_AC);

                if ($enAttente->isEmpty()) {
                    continue;
                }

                $dossier->update(['statut' => 'AJOURNE_DMG']);
                foreach ($enAttente as $paiement) {
                    $this->differerPaiement($dossier, $paiement, $auteur, $motif, 'DIFFERE_OP_AC');
                }
            }

            $this->finaliserBordereau($bordereau);
        });
    }

    /**
     * Différé partiel : portage du `ProcessDifferedStagiaire` legacy en mode « partiel ».
     * Seuls les stagiaires sélectionnés repartent à la DMG, l'OP reste ouverte tant qu'il
     * lui reste des paiements en attente de visa.
     *
     * @param  array<int, int|string>  $paiementIds
     */
    public function differerStagiaires(OrdrePaiement $ordre, User $auteur, array $paiementIds, string $motif): int
    {
        return DB::transaction(function () use ($ordre, $auteur, $paiementIds, $motif): int {
            $bordereau = $this->ordreOuvert($ordre);
            $cibles = array_map('intval', $paiementIds);
            $differes = 0;

            foreach ($ordre->dossiersPaiement as $dossier) {
                foreach ($dossier->paiementsActifs as $paiement) {
                    if (! in_array($paiement->id, $cibles, true) || $paiement->statut !== self::STATUT_ATTENTE_AC) {
                        continue;
                    }

                    $this->differerPaiement($dossier, $paiement, $auteur, $motif, 'DIFFERE_STAGIAIRE_AC');
                    $differes++;
                }
            }

            if ($differes === 0) {
                throw ValidationException::withMessages([
                    'paiement_ids' => 'Aucun des stagiaires sélectionnés n’est encore en attente de visa sur cette OP.',
                ]);
            }

            // Une OP vidée de tous ses stagiaires en attente ne peut plus être visée :
            // on la clôt en différé pour ne pas bloquer la clôture du bordereau.
            if ($this->comptePaiementsEnAttente($ordre) === 0) {
                $ordre->update(['statut' => 'DIFFERE_AC']);
                $this->finaliserBordereau($bordereau);
            }

            return $differes;
        });
    }

    public function rejeterOrdre(OrdrePaiement $ordre, User $auteur, string $motif): void
    {
        DB::transaction(function () use ($ordre, $auteur, $motif): void {
            $bordereau = $this->preparerOrdre($ordre, 'REJETE_AC');

            foreach ($ordre->dossiersPaiement as $dossier) {
                $enAttente = $dossier->paiementsActifs->where('statut', self::STATUT_ATTENTE_AC);

                if ($enAttente->isEmpty()) {
                    continue;
                }

                $dossier->update(['statut' => 'REJETE_AC']);
                foreach ($enAttente as $paiement) {
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

    /**
     * Différé d'un paiement : il repart dans la corbeille DMG avec son motif, exactement
     * comme le `user_differed` du legacy qui renvoie le stagiaire à l'agence.
     */
    private function differerPaiement(
        DossierPaiement $dossier,
        Paiement $paiement,
        User $auteur,
        string $motif,
        string $decision,
    ): void {
        $statutAvant = $paiement->statut;

        $paiement->update([
            'statut' => 'A_TRAITER',
            'corbeille_actuelle' => CorbeilleEnum::DMG_OP_DIFFERE_AC,
        ]);
        $dossier->paiements()->updateExistingPivot($paiement->id, ['motif_retrait' => $motif]);
        DecisionPaiement::enregistrer($paiement, $auteur, $decision, $motif, $statutAvant, 'A_TRAITER');
    }

    /**
     * Vérifie que l'OP est encore ouverte dans un bordereau en cours de traitement AC,
     * sans la faire changer de statut.
     */
    private function ordreOuvert(OrdrePaiement $ordre): BordereauPaiement
    {
        $ordre->loadMissing(['bordereau', 'dossiersPaiement.paiementsActifs']);
        $bordereau = $ordre->bordereau;

        if (! $bordereau || $bordereau->statut !== 'TRANSMIS_AC' || $ordre->statut !== 'EN_BORDEREAU') {
            throw ValidationException::withMessages([
                'ordre' => 'Cette OP ne dépend plus d’un bordereau en cours de traitement AC.',
            ]);
        }

        return $bordereau;
    }

    private function comptePaiementsEnAttente(OrdrePaiement $ordre): int
    {
        return $ordre->dossiersPaiement()
            ->with('paiementsActifs')
            ->get()
            ->sum(fn (DossierPaiement $dossier): int => $dossier->paiementsActifs
                ->where('statut', self::STATUT_ATTENTE_AC)
                ->count());
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

        // Le retrait renvoie toute l'OP à la DMG : il reste possible même si plus aucun
        // paiement n'attend le visa. Les autres décisions n'ont plus d'objet dans ce cas.
        if (
            $nouveauStatut !== 'BROUILLON'
            && $ordre->dossiersPaiement->sum(
                fn ($dossier): int => $dossier->paiementsActifs->where('statut', self::STATUT_ATTENTE_AC)->count(),
            ) === 0
        ) {
            throw ValidationException::withMessages([
                'ordre' => 'Cette OP ne contient plus aucun stagiaire en attente du visa de l’Agent Comptable.',
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
