<?php

namespace App\Models\Reference;

use App\Domain\Audit\Traits\Auditable;
use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SituationStage extends Model
{
    use Auditable, CachesReferenceData, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'situations_stage';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
