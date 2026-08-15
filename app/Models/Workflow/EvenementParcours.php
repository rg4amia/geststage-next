<?php

namespace App\Models\Workflow;

use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvenementParcours extends Model
{
    use HasPublicUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'evenements_parcours';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

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
        'donnees' => 'json',
        'survenu_le' => 'datetime',
    ];

    /**
     * L'instance concernée par l'événement.
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(InstanceParcours::class, 'instance_parcours_id');
    }

    /**
     * L'acteur à l'origine de l'événement.
     */
    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acteur_id');
    }
}
