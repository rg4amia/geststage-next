<?php

namespace App\Domain\Payment\Services\Prime\Strategies;

use App\Domain\Payment\Services\Prime\ContexteCalculPrime;

interface PrimeStrategyInterface
{
    /**
     * La stratégie couvre-t-elle ce stage ?
     */
    public function supports(ContexteCalculPrime $contexte): bool;

    /**
     * Prime due pour le mois donné.
     *
     * @param  string  $mois  Format « YYYY-MM » ou « YYYY-MM-DD »
     */
    public function calculate(ContexteCalculPrime $contexte, string $mois): float;

    /**
     * Ordre d'évaluation : la priorité la plus élevée l'emporte.
     */
    public function priority(): int;
}
