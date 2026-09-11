<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Reference\Periode;
use Illuminate\Database\Eloquent\Builder;

trait RestreintParPeriodeBordereau
{
    /**
     * Les OP legacy peuvent garder leur mois d'origine même quand elles ont été
     * regroupées dans un bordereau d'un autre mois. L'écran se pilote par
     * période du bordereau ; l'OP garde sa période seulement comme secours.
     */
    private function restreindreOrdreParPeriodeBordereau(Builder $ordre, Periode $periode): Builder
    {
        return $ordre->where(function (Builder $query) use ($periode): void {
            $query->where('periode_id', $periode->id)
                ->orWhereHas('bordereau', function (Builder $bordereau) use ($periode): void {
                    $bordereau->where('periode_id', $periode->id);
                });
        });
    }
}
