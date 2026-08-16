<?php

namespace App\Models\Reference;

use App\Domain\Audit\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SituationStage extends Model
{
    use HasFactory, Auditable;
    
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
