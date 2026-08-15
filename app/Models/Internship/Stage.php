<?php

namespace App\Models\Internship;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Company\Entreprise;
use App\Models\Contract\Contrat;
use App\Models\Reference\Agence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stage extends Model
{
    use HasPublicUuid, Auditable, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stages';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * L'entreprise d'accueil.
     */
    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    /**
     * Le bénéficiaire effectuant le stage.
     */
    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(Beneficiaire::class);
    }

    /**
     * L'agence en charge du stage.
     */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    /**
     * Les contrats associés à ce stage.
     */
    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }
}
