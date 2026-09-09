<?php

namespace App\Domain\Payment\Services\Prime\Strategies;

use App\Domain\Payment\Services\Prime\BaremePrime;
use App\Domain\Payment\Services\Prime\ContexteCalculPrime;
use App\Domain\Payment\Services\Prime\PrimeCalculationException;
use App\Domain\Payment\Services\Prime\PrimeConfigurationService;
use Carbon\Carbon;

/**
 * Grille du stage de qualification (`config/primes.php` → `pae`).
 *
 * Les mois pleins donnent le montant complet ; les mois de bord (celui du début
 * et celui de la fin) sont proratisés selon le jour de démarrage du contrat.
 */
class PrimeQualificationStrategy implements PrimeStrategyInterface
{
    public function __construct(private PrimeConfigurationService $configuration) {}

    public function supports(ContexteCalculPrime $contexte): bool
    {
        return $contexte->typeStageLegacyId === BaremePrime::STAGE_QUALIFICATION;
    }

    public function calculate(ContexteCalculPrime $contexte, string $mois): float
    {
        if (! $contexte->dateDebut || ! $contexte->dateFin) {
            throw new PrimeCalculationException(
                'Dates de stage incomplètes : la prime de qualification ne peut pas être proratisée.',
                context: ['stage_id' => $contexte->stageId]
            );
        }

        $dateDebut = $contexte->dateDebut;
        $dateFin = $contexte->dateFin;
        $dateMois = Carbon::parse($mois);

        $montant = $this->resolveMontant($contexte);
        $config = $this->configuration->get("pae.{$montant}");

        if (! $config) {
            throw new PrimeCalculationException(
                "Configuration de prime PAE introuvable pour montant: {$montant}",
                context: ['stage_id' => $contexte->stageId, 'montant' => $montant]
            );
        }

        // Mois intermédiaires → montant plein
        $isEdgeMonth = $dateMois->month === $dateDebut->month || $dateMois->month === $dateFin->month;
        if (! $isEdgeMonth) {
            return (float) $config['full'];
        }

        // Mois de bord → règles selon le jour de démarrage
        $rules = BaremePrime::resoudreParPlageDeJours($dateDebut->day, $config['rules']);

        if (! is_array($rules)) {
            return 0.0;
        }

        return (float) ($dateMois->month === $dateFin->month ? $rules['end'] : $rules['start']);
    }

    public function priority(): int
    {
        return 20;
    }

    /**
     * Montant mensuel de référence, qui désigne la grille à appliquer.
     *
     * La déclinaison Budget AEJ par structure n'entre en vigueur qu'à partir de
     * `qualification.budget_aej_effective_date` : les contrats antérieurs
     * restent sur la grille historique.
     */
    private function resolveMontant(ContexteCalculPrime $contexte): int
    {
        $isBudgetAEJ = BaremePrime::estBudgetAej($contexte->sourceFinancementLegacyId);
        $isBudgetEtat = BaremePrime::estBudgetEtat($contexte->sourceFinancementLegacyId);

        if (! $isBudgetAEJ) {
            return BaremePrime::PRIME_SQ;
        }

        if (! $contexte->dateDebut) {
            return BaremePrime::PRIME_SQ;
        }

        $effectiveDate = Carbon::parse(
            $this->configuration->get('qualification.budget_aej_effective_date', '2026-01-01')
        )->startOfDay();

        if ($contexte->dateDebut->copy()->startOfDay()->lt($effectiveDate)) {
            return BaremePrime::PRIME_SQ;
        }

        if ($isBudgetEtat && $contexte->typeStructureLegacyId === BaremePrime::STRUCTURE_SCAD) {
            return BaremePrime::PRIME_SQ_BUDGETETAT_SCAD;
        }

        if ($contexte->typeStructureLegacyId === BaremePrime::STRUCTURE_PUBLIC) {
            return BaremePrime::PRIME_SQ_BUDGETETAT_PUBLIC;
        }

        return BaremePrime::PRIME_SQ_BUDGETETAT_PRIVE;
    }
}
