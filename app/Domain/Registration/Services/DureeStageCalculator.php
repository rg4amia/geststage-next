<?php

namespace App\Domain\Registration\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Reproduit le calcul de date de fin du legacy (blade edit_stagiaire_modif.js,
 * fonction getDateFin) : date_fin = date_debut + durée(mois) - 1 jour, avec un cas
 * spécial pour une durée de 1,5 mois (45 jours si le stage démarre après le 1er du
 * mois, sinon 1 mois + 14 jours).
 *
 * Les types de stage n'ayant plus de "durée" en base côté Next (contrairement au
 * legacy `types_stage.duree`), la durée est un paramètre transmis par le formulaire
 * (nombre de mois choisi pour le stage) plutôt que résolue depuis un référentiel.
 */
class DureeStageCalculator
{
    public static function dateFin(string|CarbonInterface $dateDebut, float $dureeMois): Carbon
    {
        $date = Carbon::parse($dateDebut);

        if (abs($dureeMois - 1.5) < 0.001) {
            return $date->day > 1
                ? $date->copy()->addDays(45)
                : $date->copy()->addMonth()->addDays(14);
        }

        return $date->copy()->addMonthsNoOverflow((int) floor($dureeMois))->subDay();
    }
}
