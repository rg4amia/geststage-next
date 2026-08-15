<?php

namespace App\Domain\Payment\Services;

use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use App\Models\Payment\LigneDossierPaiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DmgService
{
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
                return $agenceId . '-' . $financementId;
            });

            // 3. Créer un DossierPaiement pour chaque groupe
            foreach ($groupes as $key => $paiementsGroupe) {
                [$agenceId, $financementId] = explode('-', $key);
                
                $dossier = DossierPaiement::create([
                    'uuid_public' => Str::uuid(),
                    'periode_id' => $periodeId,
                    'agence_id' => $agenceId,
                    'source_financement_id' => $financementId,
                    'numero' => 'BORD-' . date('Ym') . '-' . strtoupper(Str::random(5)),
                    'nature' => $paiementsGroupe->first()->droitPaiement->nature,
                    'statut' => 'BROUILLON',
                    'montant_total' => $paiementsGroupe->sum('montant')
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
            }
        });
    }

    /**
     * Transmet un dossier Brouillon à l'Agent Comptable.
     */
    public function transmettreDossierAc(DossierPaiement $dossier): void
    {
        DB::transaction(function () use ($dossier) {
            $dossier->update(['statut' => 'TRANSMIS_AC']);
        });
    }
}
