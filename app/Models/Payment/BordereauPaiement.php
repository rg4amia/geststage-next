<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Reference\Periode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BordereauPaiement extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid_public',
        'numero',
        'periode_id',
        'montant_total',
        'statut',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function ordresPaiement(): HasMany
    {
        return $this->hasMany(OrdrePaiement::class, 'bordereau_paiement_id');
    }
}
