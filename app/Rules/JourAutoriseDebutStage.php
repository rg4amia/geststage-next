<?php

namespace App\Rules;

use App\Models\Reference\SourceFinancement;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reprend la règle legacy SpecificDayOfMonth : la date de début de stage doit tomber
 * un jour 1, 2, 3, 4, 5, 10 ou 20 du mois, sauf pour un financement de type "Wave"
 * (paiement au fil de l'eau), où n'importe quel jour est accepté.
 */
class JourAutoriseDebutStage implements ValidationRule
{
    private const JOURS_AUTORISES = [1, 2, 3, 4, 5, 10, 20];

    public function __construct(private readonly ?int $sourceFinancementId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $source = $this->sourceFinancementId ? SourceFinancement::find($this->sourceFinancementId) : null;
        if ($source && str_contains(strtoupper((string) $source->code), 'WAVE')) {
            return;
        }

        $jour = Carbon::parse($value)->day;

        if (! in_array($jour, self::JOURS_AUTORISES, true)) {
            $fail('La date de début de stage doit être le 1, 2, 3, 4, 5, 10 ou 20 du mois.');
        }
    }
}
