<?php

namespace App\Models\Workflow;

use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Internship\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstanceParcours extends Model
{
    use HasPublicUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instances_parcours';

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
        'demarree_le' => 'datetime',
        'terminee_le' => 'datetime',
    ];

    /**
     * Le stage associé à cette instance.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * L'étape courante de l'instance.
     */
    public function etapeCourante(): BelongsTo
    {
        return $this->belongsTo(EtapeParcours::class, 'etape_courante_id');
    }

    /**
     * Les tâches de l'instance.
     */
    public function taches(): HasMany
    {
        return $this->hasMany(TacheParcours::class);
    }

    /**
     * Les tâches ouvertes de l'instance.
     */
    public function taches_ouvertes(): HasMany
    {
        return $this->hasMany(TacheParcours::class)->where('statut', 'OUVERTE');
    }

    /**
     * Les événements survenus dans cette instance.
     */
    public function evenements(): HasMany
    {
        return $this->hasMany(EvenementParcours::class);
    }
}
