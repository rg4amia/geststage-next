<?php

namespace App\Domain\Payment\Services\Prime\Strategies;

use App\Domain\Payment\Services\Prime\ContexteCalculPrime;
use App\Domain\Payment\Services\Prime\PrimeConfigurationService;
use Illuminate\Support\Facades\Log;

/**
 * Filet de sécurité : accepte tous les stages et renvoie le montant SMIG
 * configuré. Évaluée en dernier, elle évite qu'un stage sorte du moteur sans
 * prime lorsqu'aucune grille spécialisée ne le couvre.
 */
class PrimeSmigStrategy implements PrimeStrategyInterface
{
    public function __construct(private PrimeConfigurationService $configuration) {}

    public function supports(ContexteCalculPrime $contexte): bool
    {
        return true;
    }

    public function calculate(ContexteCalculPrime $contexte, string $mois): float
    {
        $montant = (float) $this->configuration->get('smig.default', 0);

        if ($montant === 0.0) {
            Log::warning('Prime SMIG à 0 F — référentiel manquant ou stage hors grille', [
                'stage_id' => $contexte->stageId,
                'type_stage_legacy_id' => $contexte->typeStageLegacyId,
                'source_financement_legacy_id' => $contexte->sourceFinancementLegacyId,
                'mois' => $mois,
            ]);
        }

        return $montant;
    }

    public function priority(): int
    {
        return 0;
    }
}
