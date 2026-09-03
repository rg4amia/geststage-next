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
     * Code de la situation « EN COURS » (SS-001), seule situation qui laisse un pointage
     * entrer dans la file DMG côté legacy (`pointage_models.situationstage_id = 1`).
     */
    public const CODE_EN_COURS = 'SS-001';

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
