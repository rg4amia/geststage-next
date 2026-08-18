<?php

namespace App\Models\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesseDoublonDecision extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'desse_doublon_decisions';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'decide_le' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(InstanceParcours::class, 'instance_parcours_id');
    }

    public function decidePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par_id');
    }
}
