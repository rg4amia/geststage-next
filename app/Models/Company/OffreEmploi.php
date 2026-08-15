<?php

namespace App\Models\Company;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OffreEmploi extends Model
{
    use HasPublicUuid, Auditable, SoftDeletes, HasFactory;

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
        return $this->belongsTo(\App\Models\Reference\Agence::class);
    }

    public function typeStage(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Reference\TypeStage::class);
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Reference\SourceFinancement::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Reference\Programme::class);
    }
}
