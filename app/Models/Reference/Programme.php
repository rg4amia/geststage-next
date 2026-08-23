<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;

class Programme extends Model
{
    use CachesReferenceData;

    protected $table = 'programmes';

    protected $guarded = [];
}
