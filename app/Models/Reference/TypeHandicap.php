<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;

class TypeHandicap extends Model
{
    use CachesReferenceData;

    protected $table = 'types_handicap';

    protected $guarded = [];
}
