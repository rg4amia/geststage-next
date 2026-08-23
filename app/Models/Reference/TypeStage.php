<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeStage extends Model
{
    use CachesReferenceData, HasFactory;

    protected $table = 'types_stage';

    protected $guarded = [];
}
