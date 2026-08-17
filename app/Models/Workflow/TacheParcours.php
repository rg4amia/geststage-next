<?php

namespace App\Models\Workflow;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class TacheParcours extends Model
{
    use Auditable, HasPublicUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'taches_parcours';

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
        'ouverte_le' => 'datetime',
        'revendiquee_le' => 'datetime',
        'fermee_le' => 'datetime',
        'echeance_le' => 'datetime',
    ];

    /**
     * L'instance associée.
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(InstanceParcours::class, 'instance_parcours_id');
    }

    /**
     * L'étape liée à la tâche.
     */
    public function etape(): BelongsTo
    {
        return $this->belongsTo(EtapeParcours::class, 'etape_parcours_id');
    }

    /**
     * Le rôle responsable.
     */
    public function roleResponsable(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_responsable_id');
    }

    /**
     * L'utilisateur ayant revendiqué la tâche.
     */
    public function revendiqueePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revendiquee_par_id');
    }
}
