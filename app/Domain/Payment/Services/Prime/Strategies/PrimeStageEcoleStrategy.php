<?php

namespace App\Domain\Payment\Services\Prime\Strategies;

use App\Domain\Payment\Services\Prime\BaremePrime;
use App\Domain\Payment\Services\Prime\ContexteCalculPrime;
use App\Domain\Payment\Services\Prime\PrimeCalculationException;
use App\Domain\Payment\Services\Prime\PrimeConfigurationService;
use Carbon\Carbon;

/**
 * Grille du stage école (`config/primes.php` → `stage_ecole`).
 *
 * Quatre cas : premier mois proratisé selon le jour de démarrage, dernier mois
 * proratisé selon la durée et le jour de démarrage, mois intermédiaires au
 * montant de base — avec un ajustement propre aux contrats de 1,5 mois.
 */
class PrimeStageEcoleStrategy implements PrimeStrategyInterface
{
    public function __construct(private PrimeConfigurationService $configuration) {}

    public function supports(ContexteCalculPrime $contexte): bool
    {
        return $contexte->typeStageLegacyId === BaremePrime::STAGE_ECOLE;
    }

    public function calculate(ContexteCalculPrime $contexte, string $mois): float
    {
        if (! $contexte->dateDebut || ! $contexte->dateFin) {
            throw new PrimeCalculationException(
                'Dates de stage incomplètes : la prime de stage école ne peut pas être proratisée.',
                context: ['stage_id' => $contexte->stageId]
            );
        }

        $dateDebut = $contexte->dateDebut;
        $dateFin = $contexte->dateFin;
        $dateMois = Carbon::parse($mois);
        $duree = $contexte->dureeMois;

        $configKey = $this->resolveConfigKey($contexte);
        $config = $this->configuration->get("stage_ecole.{$configKey}");

        if (! $config) {
            throw new PrimeCalculationException(
                "Configuration stage_ecole introuvable pour la clé: {$configKey}",
                context: ['stage_id' => $contexte->stageId, 'config_key' => $configKey]
            );
        }

        // Premier mois
        if ($dateMois->month === $dateDebut->month && $dateMois->year === $dateDebut->year) {
            return (float) BaremePrime::resoudreParPlageDeJours($dateDebut->day, $config['first_month']);
        }

        // Dernier mois
        if ($dateMois->month === $dateFin->month && $dateMois->year === $dateFin->year) {
            $durationKey = $duree == 1.5 ? 'one_point_five' : (int) $duree;

            $lastMonthRules = $config['last_month'][$durationKey]
                ?? $config['last_month']['default'];

            return (float) BaremePrime::resoudreParPlageDeJours($dateDebut->day, $lastMonthRules);
        }

        // Mois intermédiaires — ajustement propre aux contrats de 1,5 mois
        if ($duree == 1.5 && isset($config['middle_adjustment_1_5'])) {
            return (float) BaremePrime::resoudreParPlageDeJours($dateDebut->day, $config['middle_adjustment_1_5']);
        }

        return (float) $config['base'];
    }

    public function priority(): int
    {
        return 10;
    }

    /**
     * Grille applicable : par défaut la grille historique, sauf financement
     * Budget AEJ postérieur à la date d'effet, auquel cas la grille dépend du
     * type de structure et de la période de démarrage.
     */
    private function resolveConfigKey(ContexteCalculPrime $contexte): string
    {
        if (! BaremePrime::estBudgetAej($contexte->sourceFinancementLegacyId)) {
            return 'default';
        }

        if (! $contexte->dateDebut) {
            return 'default';
        }

        $dateDebut = $contexte->dateDebut->copy()->startOfDay();
        $effectiveDate = Carbon::parse(
            $this->configuration->get('stage_ecole.budget_aej_effective_date', '2026-01-01')
        )->startOfDay();

        if ($dateDebut->lt($effectiveDate)) {
            return 'default';
        }

        // Les structures SCAD ne basculent sur leur grille dédiée que sous
        // Budget État ; sous les autres financements elles restent au défaut.
        if ($contexte->typeStructureLegacyId === BaremePrime::STRUCTURE_SCAD) {
            return BaremePrime::estBudgetEtat($contexte->sourceFinancementLegacyId)
                ? 'budget_aej_scad'
                : 'default';
        }

        $structurePeriodKey = match ($contexte->typeStructureLegacyId) {
            BaremePrime::STRUCTURE_PUBLIC => 'public',
            BaremePrime::STRUCTURE_PRIVE => 'prive',
            default => 'prive',
        };

        $periods = $this->configuration->get('stage_ecole.budget_aej_periods', []);

        foreach ($periods as $period) {
            $periodStart = Carbon::parse($period['start'])->startOfDay();
            $periodEnd = isset($period['end']) && $period['end'] !== null
                ? Carbon::parse($period['end'])->endOfDay()
                : null;

            if ($dateDebut->lt($periodStart)) {
                continue;
            }

            if ($periodEnd !== null && $dateDebut->gt($periodEnd)) {
                continue;
            }

            return $period[$structurePeriodKey] ?? 'default';
        }

        return 'default';
    }
}
