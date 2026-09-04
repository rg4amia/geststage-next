<?php

namespace App\Enums;

/**
 * Visa DESSE d'un dossier de stage, porté par `stages.visa_desse`.
 *
 * Reprend `contrats_pae.etat_desse` (legacy) : 0 en attente, 1 rejeté, 2 visé. Un stage
 * dont le chef d'agence n'a pas encore validé le démarrage n'a pas de visa du tout
 * (colonne `null`) : il n'est pas encore soumis à la DESSE.
 */
enum VisaDesseEnum: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case VISE = 'VISE';
    case REJETE = 'REJETE';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente de visa DESSE',
            self::VISE => 'Visé par la DESSE',
            self::REJETE => 'Rejeté par la DESSE',
        };
    }

    /**
     * Correspondance depuis `contrats_pae.etat_desse`.
     */
    public static function depuisEtatLegacy(?int $etatDesse): ?self
    {
        return match ($etatDesse) {
            0 => self::EN_ATTENTE,
            1 => self::REJETE,
            2 => self::VISE,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
