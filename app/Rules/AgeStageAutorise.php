<?php

namespace App\Rules;

use App\Models\Reference\TypeStage;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reprend la règle d'âge du legacy (AgeForStage) : 16-40 ans pour un STAGE ECOLE,
 * 18-40 ans pour un STAGE DE QUALIFICATION. Le legacy ne branchait cette règle que
 * sur le formulaire de création ; on l'applique aussi en édition ici.
 */
class AgeStageAutorise implements ValidationRule
{
    public function __construct(private readonly ?int $typeStageId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || blank($this->typeStageId)) {
            return;
        }

        $typeStage = TypeStage::find($this->typeStageId);
        if (! $typeStage) {
            return;
        }

        $age = Carbon::parse($value)->age;

        $min = $typeStage->code === TypeStage::CODE_ECOLE ? 16 : 18;
        $max = 40;

        if ($age < $min || $age > $max) {
            $fail("L'âge du bénéficiaire ({$age} ans) doit être compris entre {$min} et {$max} ans pour ce type de stage.");
        }
    }
}
