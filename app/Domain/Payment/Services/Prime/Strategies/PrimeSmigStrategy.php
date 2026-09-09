<?php

namespace App\Domain\Payment\Services\Prime\Strategies;

use App\Domain\Payment\Services\Prime\ContexteCalculPrime;
use App\Domain\Payment\Services\Prime\PrimeConfigurationService;

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
        return (float) $this->configuration->get('smig.default', 0);
    }

    public function priority(): int
    {
        return 0;
    }
}
