<?php

namespace App\Models\Payment;

use App\Models\Company\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DossierPaiement extends Model
{
    use HasFactory;

    protected $table = 'dossiers_paiement';

    protected $fillable = [
        'uuid_public',
        'ancien_id',
        'periode_id',
        'agence_id',
        'source_financement_id',
        'numero',
        'nature',
        'statut',
        'montant_total',
        'ordre_paiement_id',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    public function paiements(): BelongsToMany
    {
        return $this->belongsToMany(
            Paiement::class,
            'lignes_dossiers_paiement',
            'dossier_paiement_id',
            'paiement_id'
        )->withPivot(['montant', 'ajoute_le', 'retire_le', 'motif_retrait']);
    }

    public function ordrePaiement(): BelongsTo
    {
        return $this->belongsTo(OrdrePaiement::class, 'ordre_paiement_id');
    }

    // Scopes pour les vues
    public function scopeBrouillon($query)
    {
        return $query->where('statut', 'BROUILLON');
    }

    public function scopeTransmisAc($query)
    {
        return $query->where('statut', 'TRANSMIS_AC');
    }

    public function scopeViseAc($query)
    {
        return $query->where('statut', 'VISE_AC');
    }

    public function scopeAjourneDmg($query)
    {
        return $query->where('statut', 'AJOURNE_DMG');
    }
}
