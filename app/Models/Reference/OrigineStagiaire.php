<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;

class OrigineStagiaire extends Model
{
    use CachesReferenceData;

    protected $table = 'origines_stagiaire';

    protected $guarded = [];
}
