<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;

class TypePaiement extends Model
{
    use CachesReferenceData;

    protected $table = 'types_paiement';

    protected $guarded = [];
}
