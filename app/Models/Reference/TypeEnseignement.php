<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;

class TypeEnseignement extends Model
{
    use CachesReferenceData;

    protected $table = 'types_enseignement';

    protected $guarded = [];
}
