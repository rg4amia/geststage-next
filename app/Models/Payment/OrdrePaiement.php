<?php

namespace App\Models\Payment;

use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdrePaiement extends Model
{
    protected $fillable = [
        'uuid_public',
        'ancien_id',
        'numero',
        'libelle',
        'periode_id',
        'source_financement_id',
        'montant_total',
        'montant_etat_financement',
        'statut',
        'bordereau_paiement_id',
    ];

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** @return BelongsTo<SourceFinancement, $this> */
    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
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
