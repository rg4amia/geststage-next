<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;

class NiveauEtude extends Model
{
    use CachesReferenceData;

    protected $table = 'niveaux_etude';

    protected $guarded = [];
}
