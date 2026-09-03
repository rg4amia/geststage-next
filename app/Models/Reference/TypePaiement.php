<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TypePaiement extends Model
{
    use CachesReferenceData;

    /**
     * Codes issus de la migration legacy (`type_paiement.id` 1 et 2). Le libellé, lui, varie
     * selon les jeux de données (« TRESOR MONEY », « Trésor Money »…) : d'où la double
     * reconnaissance code + nom dans les helpers ci-dessous.
     */
    public const CODE_TRESOR_MONEY = 'TP-001';

    public const CODE_WAVE = 'TP-002';

    protected $table = 'types_paiement';

    protected $guarded = [];

    public function estTresorMoney(): bool
    {
        return $this->code === self::CODE_TRESOR_MONEY
            || Str::contains(Str::upper((string) $this->nom), 'TRESOR');
    }

    public function estWave(): bool
    {
        return $this->code === self::CODE_WAVE
            || Str::contains(Str::upper((string) $this->nom), 'WAVE');
    }
}
