<?php

namespace App\Domain\Payment\Services\Prime\Strategies;

use App\Domain\Payment\Services\Prime\BaremePrime;
use App\Domain\Payment\Services\Prime\ContexteCalculPrime;

/**
 * Forfait de 150 000 F pour les stagiaires qui réunissent simultanément :
 * financement PAPS-GOUV, stage de qualification, et entreprise dont la raison
 * sociale contient « mirah ».
 *
 * Priorité supérieure à la grille de qualification (20) : les stagiaires
 * concernés touchent le forfait et non le montant proratisé.
 */
class PrimeMirahStrategy implements PrimeStrategyInterface
{
    public function supports(ContexteCalculPrime $contexte): bool
    {
        if ($contexte->typeStageLegacyId !== BaremePrime::STAGE_QUALIFICATION) {
            return false;
        }

        if ($contexte->sourceFinancementLegacyId !== BaremePrime::FINANCEMENT_PAPS_GOUV) {
            return false;
        }

        return $contexte->nomEntreprise !== null
            && str_contains(mb_strtolower($contexte->nomEntreprise), 'mirah');
    }

    public function calculate(ContexteCalculPrime $contexte, string $mois): float
    {
        return (float) BaremePrime::PRIME_ENTREPRISE_MIRAH;
    }

    public function priority(): int
    {
        return 30;
    }
}
