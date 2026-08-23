<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;

class Commune extends Model
{
    use CachesReferenceData;

    protected $table = 'communes';

    protected $guarded = [];
}
