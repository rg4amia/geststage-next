<?php

namespace App\Domain\Payment\Services\Prime;

/**
 * Identifiants legacy et montants de référence du barème.
 *
 * Transcription de `TypeStageEnum`, `TypeFinancement`, `TypeStructureEnum` et
 * `MontantPrimeBudgetAEJ` (legacy). Les identifiants sont ceux du legacy : les
 * contextes de calcul les rétablissent depuis `ancien_id`.
 *
 * @see ContexteCalculPrime
 */
final class BaremePrime
{
    // Types de stage
    public const STAGE_QUALIFICATION = 1;

    public const STAGE_ECOLE = 2;

    // Sources de financement. BUDGET_AEJ_ALT porte le « Budget État » ; c'est
    // la seule des deux valeurs Budget AEJ que Next ait reprise (ancien_id 3).
    public const FINANCEMENT_PAPS_GOUV = 1;

    public const FINANCEMENT_BUDGET_AEJ = 2;

    public const FINANCEMENT_BUDGET_AEJ_ALT = 3;

    // Types de structure
    public const STRUCTURE_PUBLIC = 1;

    public const STRUCTURE_PRIVE = 2;

    public const STRUCTURE_SCAD = 3;

    // Montants mensuels de référence
    public const PRIME_SQ = 45000;

    public const PRIME_SQ_BUDGETETAT_PUBLIC = 75000;

    public const PRIME_SQ_BUDGETETAT_PRIVE = 45000;

    public const PRIME_SQ_BUDGETETAT_SCAD = 45000;

    public const PRIME_ENTREPRISE_MIRAH = 150000;

    /**
     * Résout une valeur dans une grille indexée par jour ou par plage de jours
     * (« 1-5 », « 10-19 », « 20 »).
     *
     * Transcription de `resolveByDayRange()` (legacy helpers.php), y compris son
     * retour à 0 lorsque aucune plage ne couvre le jour : les jours hors grille
     * n'ouvrent pas droit à prime.
     *
     * @param  array<string, mixed>  $rules
     */
    public static function resoudreParPlageDeJours(int $jour, array $rules): mixed
    {
        foreach ($rules as $range => $value) {
            if (str_contains((string) $range, '-')) {
                [$min, $max] = explode('-', (string) $range);
                if ($jour >= (int) $min && $jour <= (int) $max) {
                    return $value;
                }
            }

            if ((int) $range === $jour) {
                return $value;
            }
        }

        return 0;
    }

    public static function estBudgetAej(?int $sourceFinancementLegacyId): bool
    {
        return in_array($sourceFinancementLegacyId, [
            self::FINANCEMENT_BUDGET_AEJ,
            self::FINANCEMENT_BUDGET_AEJ_ALT,
        ], true);
    }

    public static function estBudgetEtat(?int $sourceFinancementLegacyId): bool
    {
        return $sourceFinancementLegacyId === self::FINANCEMENT_BUDGET_AEJ_ALT;
    }
}
