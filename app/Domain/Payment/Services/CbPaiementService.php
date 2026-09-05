<?php

namespace App\Domain\Payment\Services;

use App\Models\Payment\DecisionPaiement;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\LigneDossierPaiement;
use App\Models\Payment\Paiement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CbPaiementService
{
    public function validerDossier(DossierPaiement $dossier, User $auteur): int
    {
        return DB::transaction(function () use ($dossier, $auteur): int {
            $dossier = $this->dossierATraiter($dossier);
            $paiements = $this->paiementsActifs($dossier);

            if ($paiements->isEmpty()) {
                throw ValidationException::withMessages([
                    'dossier' => 'Ce dossier ne contient aucun paiement actif à traiter.',
                ]);
            }

            $dossier->update(['statut' => 'VALIDE_CB']);

            foreach ($paiements as $paiement) {
                DecisionPaiement::enregistrer(
                    $paiement,
                    $auteur,
                    'VALIDATION_DOSSIER_CB',
                    null,
                    $paiement->statut,
                    $paiement->statut,
                );
            }

            return $paiements->count();
        });
    }

    public function ajournerDossier(DossierPaiement $dossier, User $auteur, string $motif): int
    {
        return DB::transaction(function () use ($dossier, $auteur, $motif): int {
            $dossier = $this->dossierATraiter($dossier);
            $paiements = $this->paiementsActifs($dossier);

            if ($paiements->isEmpty()) {
                throw ValidationException::withMessages([
                    'dossier' => 'Ce dossier ne contient aucun paiement actif à ajourner.',
                ]);
            }

            $dossier->update(['statut' => 'AJOURNE_CB']);
            LigneDossierPaiement::query()
                ->where('dossier_paiement_id', $dossier->id)
                ->whereNull('retire_le')
                ->update(['motif_retrait' => $motif]);

            foreach ($paiements as $paiement) {
                DecisionPaiement::enregistrer(
                    $paiement,
                    $auteur,
                    'AJOURNEMENT_DOSSIER_CB',
                    $motif,
                    $paiement->statut,
                    $paiement->statut,
                );
            }

            return $paiements->count();
        });
    }

    private function dossierATraiter(DossierPaiement $dossier): DossierPaiement
    {
        $dossier = DossierPaiement::query()
            ->lockForUpdate()
            ->findOrFail($dossier->id);

        if ($dossier->statut !== 'TRANSMIS_CB' || $dossier->ordre_paiement_id !== null) {
            throw ValidationException::withMessages([
                'dossier' => 'Ce dossier n’est plus en attente de traitement CB.',
            ]);
        }

        return $dossier;
    }

    private function paiementsActifs(DossierPaiement $dossier): Collection
    {
        return Paiement::query()
            ->whereHas('dossiersPaiement', fn ($query) => $query
                ->where('dossiers_paiement.id', $dossier->id)
                ->whereNull('lignes_dossiers_paiement.retire_le'))
            ->where('statut', 'EN_DOSSIER')
            ->lockForUpdate()
            ->get();
    }
}
