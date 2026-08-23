<?php

namespace App\Models\Company;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Concerns\CachesReferenceData;
use App\Models\Reference\Agence;
use App\Models\Reference\Commune;
use App\Models\Reference\TypeStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entreprise extends Model
{
    use Auditable, CachesReferenceData, HasFactory, HasPublicUuid, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entreprises';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * L'agence responsable de l'entreprise.
     */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    /**
     * La commune où est située l'entreprise.
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * Le type de structure de l'entreprise.
     */
    public function typeStructure(): BelongsTo
    {
        return $this->belongsTo(TypeStructure::class);
    }

    /**
     * Les offres d'emploi rattachées à l'entreprise.
     */
    public function offresEmploi(): HasMany
    {
        return $this->hasMany(OffreEmploi::class);
    }
}
