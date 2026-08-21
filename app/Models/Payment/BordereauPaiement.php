<?php

namespace App\Models\Payment;

use App\Models\Reference\Periode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BordereauPaiement extends Model
{
    protected $fillable = [
        'uuid_public',
        'ancien_id',
        'numero',
        'periode_id',
        'source_financement_id',
        'montant_total',
        'statut',
    ];

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** @return HasMany<OrdrePaiement, $this> */
    public function ordresPaiement(): HasMany
    {
        return $this->hasMany(OrdrePaiement::class, 'bordereau_paiement_id');
    }
}
