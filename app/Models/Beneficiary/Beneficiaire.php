<?php

namespace App\Models\Beneficiary;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Internship\Stage;
use App\Models\Reference\Commune;
use App\Models\Reference\Diplome;
use App\Models\Reference\Handicap;
use App\Models\Reference\NiveauEtude;
use App\Models\Reference\TypeHandicap;
use App\Models\Reference\TypePaiement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Beneficiaire extends Model
{
    use Auditable, HasFactory, HasPublicUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'beneficiaires';

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
        'date_naissance' => 'date',
        'annee_diplome' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Les stages du bénéficiaire.
     */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    public function communeResidence()
    {
        return $this->belongsTo(Commune::class, 'commune_residence_id');
    }

    public function diplome()
    {
        return $this->belongsTo(Diplome::class, 'diplome_id');
    }

    /**
     * Le moyen de paiement (Wave, Trésor Money, ...) du bénéficiaire.
     */
    public function typePaiement(): BelongsTo
    {
        return $this->belongsTo(TypePaiement::class);
    }

    public function niveauEtude(): BelongsTo
    {
        return $this->belongsTo(NiveauEtude::class, 'niveau_etude_id');
    }

    public function handicap(): BelongsTo
    {
        return $this->belongsTo(Handicap::class, 'handicap_id');
    }

    public function typeHandicap(): BelongsTo
    {
        return $this->belongsTo(TypeHandicap::class, 'type_handicap_id');
    }
}
