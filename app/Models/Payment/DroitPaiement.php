<?php

namespace App\Models\Payment;

use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Attendance\Pointage;
use App\Models\Internship\Stage;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Audit\Traits\Auditable;

class DroitPaiement extends Model
{
    use HasFactory, HasPublicUuid, Auditable;

    protected $table = 'droits_paiement';

    protected $guarded = [];

    protected $casts = [
        'ouvert_le' => 'datetime',
        'annule_le' => 'datetime',
    ];

    /**
     * Le stage qui génère ce droit au paiement.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Le pointage (présence mensuelle) ayant généré ce droit (si applicable).
     */
    public function pointage(): BelongsTo
    {
        return $this->belongsTo(Pointage::class);
    }

    /**
     * La période de couverture de ce paiement.
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /**
     * La source de financement imputée.
     */
    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    /**
     * Les paiements générés à partir de ce droit.
     */
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }
}
