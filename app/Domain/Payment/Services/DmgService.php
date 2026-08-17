<?php

namespace App\Domain\Payment\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\LigneDossierPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DmgService
{
    public function __construct(private WorkflowTransitionService $workflowService) {}

    /**
     * Regroupe les paiements en attente ("A_TRAITER") en Dossiers de Paiement.
     * Le regroupement se fait par : Période, Agence, Source de Financement.
     */
    public function genererDossiersPaiement(int $periodeId): void
    {
        DB::transaction(function () use ($periodeId) {
            // 1. Récupérer tous les paiements A_TRAITER pour la période donnée
            $paiements = Paiement::where('statut', 'A_TRAITER')
                ->whereHas('droitPaiement', function ($q) use ($periodeId) {
                    $q->where('periode_id', $periodeId);
                })
                ->with(['droitPaiement.stage']) // Eager load pour grouper
                ->get();

            if ($paiements->isEmpty()) {
                return;
            }

            // 2. Grouper par [Agence - Source Financement]
            $groupes = $paiements->groupBy(function ($p) {
                $agenceId = $p->droitPaiement->stage->agence_id;
                $financementId = $p->droitPaiement->stage->source_financement_id;

                return $agenceId.'-'.$financementId;
            });

            // 3. Créer un DossierPaiement pour chaque groupe
            foreach ($groupes as $key => $paiementsGroupe) {
                [$agenceId, $financementId] = explode('-', $key);

                $dossier = DossierPaiement::create([
                    'uuid_public' => Str::uuid(),
                    'periode_id' => $periodeId,
                    'agence_id' => $agenceId,
                    'source_financement_id' => $financementId,
                    'numero' => 'BORD-'.date('Ym').'-'.strtoupper(Str::random(5)),
                    'nature' => $paiementsGroupe->first()->droitPaiement->nature,
                    'statut' => 'BROUILLON',
                    'montant_total' => $paiementsGroupe->sum('montant'),
                ]);

                // 4. Attacher les paiements via la table pivot
                foreach ($paiementsGroupe as $paiement) {
                    LigneDossierPaiement::create([
                        'dossier_paiement_id' => $dossier->id,
                        'paiement_id' => $paiement->id,
                        'montant' => $paiement->montant,
                    ]);

                    // Marquer le paiement comme EN_COURS (lié à un dossier)
                    $paiement->update(['statut' => 'EN_COURS']);
                }

                $this->workflowService->dmgElaboreDossier($dossier);
            }
        });
    }

    /**
     * Transmet un dossier Brouillon au Chef de Bureau (CB).
     */
    public function transmettreDossierCb(DossierPaiement $dossier): void
    {
        DB::transaction(function () use ($dossier) {
            $dossier->update(['statut' => 'TRANSMIS_CB']);
        });
    }

    /**
     * Retrait d'un paiement d'un dossier
     */
    public function retirerPaiementDossier(DossierPaiement $dossier, Paiement $paiement, string $motif = ''): void
    {
        DB::transaction(function () use ($dossier, $paiement, $motif) {
            $dossier->paiements()->updateExistingPivot($paiement->id, [
                'retire_le' => now(),
                'motif_retrait' => $motif,
            ]);
            $paiement->update(['statut' => 'A_TRAITER']);

            // Recalculer le montant
            $montantRetire = $dossier->paiements()->where('paiement_id', $paiement->id)->first()->pivot->montant ?? 0;
            $dossier->update([
                'montant_total' => $dossier->montant_total - $montantRetire,
            ]);
        });
    }

    /**
     * Élabore un OP à partir de plusieurs dossiers validés par le CB.
     */
    public function elaborerOp(array $dossierIds, int $periodeId): OrdrePaiement
    {
        return DB::transaction(function () use ($dossierIds, $periodeId) {
            $dossiers = DossierPaiement::whereIn('id', $dossierIds)->where('statut', 'VALIDE_CB')->get();
            $montantTotal = $dossiers->sum('montant_total');

            $op = OrdrePaiement::create([
                'uuid_public' => Str::uuid(),
                'numero' => 'OP-'.date('Ym').'-'.strtoupper(Str::random(5)),
                'periode_id' => $periodeId,
                'montant_total' => $montantTotal,
                'statut' => 'BROUILLON',
            ]);

            foreach ($dossiers as $dossier) {
                $dossier->update([
                    'ordre_paiement_id' => $op->id,
                    'statut' => 'EN_OP',
                ]);
            }

            $this->workflowService->dmgElaboreOp($op);

            return $op;
        });
    }

    /**
     * Crée un Bordereau à partir de plusieurs OP.
     */
    public function creerBordereau(array $opIds, int $periodeId): BordereauPaiement
    {
        return DB::transaction(function () use ($opIds, $periodeId) {
            $ops = OrdrePaiement::whereIn('id', $opIds)->where('statut', 'BROUILLON')->get();
            $montantTotal = $ops->sum('montant_total');

            $bordereau = BordereauPaiement::create([
                'uuid_public' => Str::uuid(),
                'numero' => 'BORD-'.date('Ym').'-'.strtoupper(Str::random(5)),
                'periode_id' => $periodeId,
                'montant_total' => $montantTotal,
                'statut' => 'BROUILLON',
            ]);

            foreach ($ops as $op) {
                $op->update([
                    'bordereau_paiement_id' => $bordereau->id,
                    'statut' => 'EN_BORDEREAU',
                ]);
            }

            return $bordereau;
        });
    }

    /**
     * Transmet un Bordereau à l'Agent Comptable.
     */
    public function transmettreBordereauAc(BordereauPaiement $bordereau): void
    {
        DB::transaction(function () use ($bordereau) {
            $bordereau->update(['statut' => 'TRANSMIS_AC']);
            $this->workflowService->dmgTransmetBordereauAc($bordereau);
        });
    }
}
