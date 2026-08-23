<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;

class LienParente extends Model
{
    use CachesReferenceData;

    protected $table = 'liens_parente';

    protected $guarded = [];
}
