<?php

namespace App\Models\Company;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Reference\Agence;
use App\Models\Reference\Programme;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OffreEmploi extends Model
{
    use Auditable, HasFactory, HasPublicUuid, SoftDeletes;

    protected $table = 'offres_emploi';

    protected $guarded = [];

    protected $casts = [
        'publiee_le' => 'date',
        'valide_du' => 'date',
        'valide_au' => 'date',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function typeStage(): BelongsTo
    {
        return $this->belongsTo(TypeStage::class);
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
