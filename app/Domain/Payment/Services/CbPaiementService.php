<?php

namespace App\Domain\Payment\Services;

use App\Models\Payment\DecisionPaiement;
use App\Models\Payment\DossierGroupe;
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
        return DB::transaction(fn (): int => $this->validerDossierCore($dossier, $auteur));
    }

    public function ajournerDossier(DossierPaiement $dossier, User $auteur, string $motif): int
    {
        return DB::transaction(fn (): int => $this->ajournerDossierCore($dossier, $auteur, $motif));
    }

    public function validerGroupe(DossierGroupe $groupe, User $auteur): int
    {
        return DB::transaction(function () use ($groupe, $auteur): int {
            $locked = DossierGroupe::query()->lockForUpdate()->with('dossiers')->findOrFail($groupe->id);
            if ($locked->statut !== 'TRANSMIS_CB' || $locked->dossiers->isEmpty()) {
                throw ValidationException::withMessages([
                    'groupe' => 'Ce multi-dossier n’est plus en attente de traitement CB.',
                ]);
            }

            $total = 0;
            foreach ($locked->dossiers as $dossier) {
                $total += $this->validerDossierCore($dossier, $auteur);
            }
            $locked->update(['statut' => 'VALIDE_CB']);

            return $total;
        });
    }

    public function ajournerGroupe(DossierGroupe $groupe, User $auteur, string $motif): int
    {
        return DB::transaction(function () use ($groupe, $auteur, $motif): int {
            $locked = DossierGroupe::query()->lockForUpdate()->with('dossiers')->findOrFail($groupe->id);
            if ($locked->statut !== 'TRANSMIS_CB' || $locked->dossiers->isEmpty()) {
                throw ValidationException::withMessages([
                    'groupe' => 'Ce multi-dossier n’est plus en attente de traitement CB.',
                ]);
            }

            $total = 0;
            foreach ($locked->dossiers as $dossier) {
                $total += $this->ajournerDossierCore($dossier, $auteur, $motif);
            }
            $locked->update(['statut' => 'AJOURNE_CB']);

            return $total;
        });
    }

    /**
     * Ajournement partiel : retire du dossier les seuls paiements sélectionnés, sans rejeter
     * le dossier entier. Le dossier reste `TRANSMIS_CB` et peut ensuite être validé normalement
     * pour le reste de ses paiements actifs. Le paiement retiré repart en file d'attente DMG,
     * comme un paiement ajourné classique (cf. `DmgService::retirerPaiementDossier()`).
     *
     * @param  list<int>  $paiementIds
     */
    public function ajournerPaiements(array $paiementIds, User $auteur, string $motif): int
    {
        return DB::transaction(function () use ($paiementIds, $auteur, $motif): int {
            $ids = array_values(array_unique($paiementIds));

            $lignes = LigneDossierPaiement::query()
                ->lockForUpdate()
                ->whereIn('paiement_id', $ids)
                ->whereNull('retire_le')
                ->get();

            if ($lignes->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'paiement_ids' => 'Selection de paiements invalide.',
                ]);
            }

            $dossierIds = $lignes->pluck('dossier_paiement_id')->unique();
            $dossiers = DossierPaiement::query()->lockForUpdate()->whereIn('id', $dossierIds)->get()->keyBy('id');

            foreach ($dossierIds as $dossierId) {
                $dossier = $dossiers->get($dossierId);
                if (! $dossier || $dossier->statut !== 'TRANSMIS_CB' || $dossier->ordre_paiement_id !== null) {
                    throw ValidationException::withMessages([
                        'paiement_ids' => 'Un des dossiers concernes n’est plus en attente de traitement CB.',
                    ]);
                }

                $actifsIds = $this->paiementsActifs($dossier)->pluck('id');
                $retiresIds = $lignes->where('dossier_paiement_id', $dossierId)->pluck('paiement_id');
                if ($actifsIds->diff($retiresIds)->isEmpty()) {
                    throw ValidationException::withMessages([
                        'paiement_ids' => 'Le dossier doit conserver au moins un paiement actif ; ajournez le dossier entier si necessaire.',
                    ]);
                }
            }

            $paiements = Paiement::query()->lockForUpdate()->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($lignes as $ligne) {
                $paiement = $paiements->get($ligne->paiement_id);
                $dossier = $dossiers->get($ligne->dossier_paiement_id);

                $ligne->update(['retire_le' => now(), 'motif_retrait' => $motif]);
                $dossier->decrement('montant_total', $ligne->montant);
                $paiement->update(['statut' => 'A_TRAITER']);

                DecisionPaiement::enregistrer($paiement, $auteur, 'AJOURNEMENT_STAGIAIRE_CB', $motif, 'EN_DOSSIER', 'A_TRAITER');
            }

            return $lignes->count();
        });
    }

    private function validerDossierCore(DossierPaiement $dossier, User $auteur): int
    {
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
    }

    private function ajournerDossierCore(DossierPaiement $dossier, User $auteur, string $motif): int
    {
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
