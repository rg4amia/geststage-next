<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;

class DefinitionParcours extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'definitions_parcours';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
