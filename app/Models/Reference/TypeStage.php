<?php

namespace App\Models\Reference;

use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeStage extends Model
{
    use CachesReferenceData, HasFactory;

    protected $table = 'types_stage';

    protected $guarded = [];

    /** Legacy `type_stage.id = 1`. */
    public const CODE_QUALIFICATION = 'STAGE_DE_QUALIFICATION';

    /** Legacy `type_stage.id = 4`, doublon historique du précédent, conservé inactif. */
    public const CODE_QUALIFICATION_HERITE = 'STAGE_DE_QUALIFICATION_4';

    /** Legacy `type_stage.id = 2` : non renouvelable. */
    public const CODE_ECOLE = 'STAGE_ECOLE';

    /**
     * Identifiants des types de stage renouvelables, résolus par code pour ne pas
     * dépendre de l'ordre d'insertion des référentiels.
     *
     * @param  array<int, string>  $codes
     * @return array<int, int>
     */
    public static function idsPourCodes(array $codes): array
    {
        return static::query()->whereIn('code', $codes)->pluck('id')->all();
    }
}
