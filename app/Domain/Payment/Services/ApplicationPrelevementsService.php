<?php

namespace App\Domain\Payment\Services;

use App\Models\Payment\Paiement;
use App\Models\Payment\ReglePrelevement;
use App\Models\Reference\ParametreSysteme;
use App\Models\Reference\Periode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Résolution et application des règles de prélèvement (cotisation CMU) d'un paiement.
 *
 * Portage de `App\Services\PaymentDeductionService` (legacy) : la prime calculée
 * par le barème devient un montant **brut**, dont une part peut être prélevée
 * (cotisation CMU) selon une règle datée, par source de financement, type de
 * stage et type de paiement (démarrage ou présence). Le paiement conserve cet
 * instantané (brut / prélèvement / net) pour que CB et AC puissent vérifier la
 * trajectoire complète du dossier sans recalcul.
 *
 * Le montant historique `paiements.montant` reste le montant **net** versé :
 * aucun paiement existant n'est réécrit, seuls les paiements A_TRAITER
 * sélectionnés reçoivent l'instantané.
 */
class ApplicationPrelevementsService
{
    /**
     * Borne haute conventionnelle d'une règle sans date de fin, alignée sur
     * ReglePrelevement::FIN_OUVERTE (copie locale pour éviter le couplage au
     * modèle dans les requêtes brutes).
     */
    private const FIN_OUVERTE = '9999-12-31';

    /**
     * Règle active couvrant le mois de la période, pour la source de financement
     * et le type de stage du stage — portage de findApplicableRule().
     *
     * Comme dans le legacy, une règle ciblant explicitement le type de stage
     * l'emporte sur une règle « tous types » posée à la même date.
     */
    public function regleApplicable(
        ?int $sourceFinancementId,
        ?int $typeStageId,
        ?Periode $periode,
        string $typePaiement = ReglePrelevement::PAIEMENT_DEMARRAGE,
    ): ?ReglePrelevement {
        $moisDebut = $periode?->date_debut
            ? Carbon::parse($periode->date_debut)->startOfMonth()
            : ($periode?->code ? Carbon::parse($periode->code.'-01')->startOfMonth() : null);

        if ($sourceFinancementId === null || $moisDebut === null) {
            return null;
        }

        $moisFin = $moisDebut->copy()->endOfMonth();

        return ReglePrelevement::query()
            ->where('actif', true)
            ->where('montant', '>', 0)
            ->where('source_financement_id', $sourceFinancementId)
            ->where('type_paiement', $typePaiement)
            ->whereDate('effet_du', '<=', $moisFin)
            ->where(function (Builder $query) use ($moisDebut): void {
                $query->whereNull('effet_au')
                    ->orWhereDate('effet_au', '>=', $moisDebut);
            })
            ->where(function (Builder $query) use ($typeStageId): void {
                $query->whereNull('type_stage_id');

                if ($typeStageId !== null) {
                    $query->orWhere('type_stage_id', $typeStageId);
                }
            })
            ->orderByRaw('CASE WHEN type_stage_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('effet_du')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Décomposition brut / prélèvement / net d'une prime calculée. Le prélèvement
     * ne peut jamais excéder le brut (portage de calculate()).
     *
     * @return array{brut: float, prelevement: float, net: float, type: ?string, regle: ?ReglePrelevement}
     */
    public function decomposer(
        float $montantBrut,
        ?int $sourceFinancementId,
        ?int $typeStageId,
        ?Periode $periode,
        string $typePaiement = ReglePrelevement::PAIEMENT_DEMARRAGE,
    ): array {
        $regle = $this->regleApplicable($sourceFinancementId, $typeStageId, $periode, $typePaiement);

        $prelevement = $regle
            ? min(max(0.0, $montantBrut), max(0.0, (float) $regle->montant))
            : 0.0;

        return [
            'brut' => $montantBrut,
            'prelevement' => $prelevement,
            'net' => max(0.0, $montantBrut - $prelevement),
            'type' => $regle?->type_prelevement,
            'regle' => $regle,
        ];
    }

    /**
     * Pose l'instantané sur un paiement existant (brut, prélèvement, net) : le
     * montant du paiement devient le net, le brut et le prélèvement sont figés
     * pour la traçabilité CB/AC.
     */
    public function appliquer(Paiement $paiement, float $montantBrut, ReglePrelevement $regle): Paiement
    {
        $prelevement = min(max(0.0, $montantBrut), max(0.0, (float) $regle->montant));

        $paiement->forceFill([
            'montant_brut' => $montantBrut,
            'montant_prelevement' => $prelevement,
            'type_prelevement' => $regle->type_prelevement,
            'regle_prelevement_id' => $regle->id,
            'montant' => max(0.0, $montantBrut - $prelevement),
        ]);
        $paiement->save();

        return $paiement;
    }

    /**
     * Applique les règles actives aux paiements A_TRAITER dont le droit de
     * paiement porte un montant brut et une période. Renvoie le nombre de
     * paiements mis à jour.
     *
     * Utilisé par le recalcul (`primes:recalculer`) et par l'écran d'administration
     * pour rattraper les paiements générés avant l'activation d'une règle.
     */
    public function appliquerAuxPaiementsEnAttente(?string $mois = null, ?string $nature = null): int
    {
        $paiements = Paiement::query()
            ->where('statut', 'A_TRAITER')
            ->whereNull('regle_prelevement_id')
            ->whereHas('droitPaiement', function (Builder $droit) use ($mois, $nature): void {
                $droit->whereNull('annule_le')
                    ->when($nature !== null, fn (Builder $q) => $q->where('nature', $nature))
                    ->when($mois !== null, fn (Builder $q) => $q->whereHas(
                        'periode',
                        fn (Builder $p) => $p->where('code', $mois),
                    ));
            })
            ->with(['droitPaiement.stage', 'droitPaiement.periode'])
            ->get();

        $traites = 0;

        foreach ($paiements as $paiement) {
            $droit = $paiement->droitPaiement;
            $stage = $droit?->stage;

            if (! $droit || ! $stage) {
                continue;
            }

            $regle = $this->regleApplicable(
                $droit->source_financement_id,
                $stage->type_stage_id,
                $droit->periode,
                $this->typePaiementDeNature((string) $droit->nature),
            );

            if (! $regle) {
                continue;
            }

            $this->appliquer($paiement, (float) $paiement->montant, $regle);
            $traites++;
        }

        if ($traites > 0) {
            Log::info('Prélèvements appliqués aux paiements en attente.', ['total' => $traites, 'mois' => $mois]);
        }

        return $traites;
    }

    /**
     * Nature du droit → type de paiement attendu par les règles. Toute nature
     * inconnue est traitée comme de la présence : seul le démarrage porte un
     * prélèvement historique.
     */
    public function typePaiementDeNature(string $nature): string
    {
        return $nature === 'DEMARRAGE'
            ? ReglePrelevement::PAIEMENT_DEMARRAGE
            : ReglePrelevement::PAIEMENT_PRESENCE;
    }

    /**
     * Bascule `cmu_obligatoire` des paramètres système : quand elle est active,
     * une règle CMU sans fin couvre les paiements de démarrage même si sa période
     * de validité théorique est dépassée. Conservé pour l'écran de paramétrage.
     */
    public function cmuObligatoire(): bool
    {
        try {
            $parametre = ParametreSysteme::query()->where('cle', 'cmu_obligatoire')->first();

            return (bool) $parametre?->valeur_typee;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Sources de financement couvertes par au moins une règle active (utilisé
     * pour l'affichage dans les corbeilles).
     *
     * @return array<int, int>
     */
    public function sourcesCouvertes(): array
    {
        return ReglePrelevement::query()
            ->where('actif', true)
            ->pluck('source_financement_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Libellé court du type de prélèvement pour les écrans.
     */
    public static function libelleType(?string $type): string
    {
        return match ($type) {
            'CMU' => 'CMU',
            null => '-',
            default => ucfirst(strtolower($type)),
        };
    }
}
