<?php

namespace App\Models\Payment;

use App\Models\Reference\Periode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdrePaiement extends Model
{
    protected $fillable = [
        'uuid_public',
        'ancien_id',
        'numero',
        'periode_id',
        'source_financement_id',
        'montant_total',
        'statut',
        'bordereau_paiement_id',
    ];

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** @return BelongsTo<BordereauPaiement, $this> */
    public function bordereau(): BelongsTo
    {
        return $this->belongsTo(BordereauPaiement::class, 'bordereau_paiement_id');
    }

    /** @return HasMany<DossierPaiement, $this> */
    public function dossiersPaiement(): HasMany
    {
        return $this->hasMany(DossierPaiement::class, 'ordre_paiement_id');
    }
}
