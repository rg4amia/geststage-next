<?php

namespace App\Models\Internship;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Attendance\Pointage;
use App\Models\Beneficiary\Beneficiaire;
use App\Models\Company\Entreprise;
use App\Models\Contract\Contrat;
use App\Models\Document\Document;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    use Auditable, HasFactory, HasPublicUuid;

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
     * La source de financement liée au stage.
     */
    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    /**
     * Le type de stage.
     */
    public function typeStage(): BelongsTo
    {
        return $this->belongsTo(TypeStage::class);
    }

    /**
     * Les contrats associés à ce stage.
     */
    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    /**
     * Les pointages liés à ce stage.
     */
    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class);
    }

    /**
     * Les documents (pièces justificatives) rattachés à ce stage.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * L'instance de parcours (workflow) liée à ce stage.
     */
    public function instanceParcours()
    {
        return $this->hasOne(InstanceParcours::class);
    }
}
