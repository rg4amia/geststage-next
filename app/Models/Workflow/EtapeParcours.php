<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class EtapeParcours extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'etapes_parcours';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Le rôle responsable de cette étape.
     */
    public function roleResponsable(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_responsable_id');
    }
}
