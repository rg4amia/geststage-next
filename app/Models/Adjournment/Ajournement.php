<?php

namespace App\Models\Adjournment;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\User;
use App\Models\Workflow\EtapeParcours;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Ajournement extends Model
{
    use Auditable, HasPublicUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ajournements';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * L'objet ajourné.
     */
    public function objet(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'objet_ajourne_type', 'objet_ajourne_id');
    }

    /**
     * L'auteur de l'ajournement.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /**
     * L'étape d'origine de l'ajournement.
     */
    public function etapeOrigine(): BelongsTo
    {
        return $this->belongsTo(EtapeParcours::class, 'etape_origine_id');
    }

    /**
     * L'étape où la correction doit avoir lieu.
     */
    public function etapeCorrection(): BelongsTo
    {
        return $this->belongsTo(EtapeParcours::class, 'etape_correction_id');
    }

    /**
     * L'étape à laquelle le dossier doit revenir après correction.
     */
    public function etapeRetour(): BelongsTo
    {
        return $this->belongsTo(EtapeParcours::class, 'etape_retour_id');
    }
}
