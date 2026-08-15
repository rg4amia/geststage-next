<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Domain\Audit\Traits\Auditable;

class Paiement extends Model
{
    use HasFactory, Auditable;

    protected $table = 'paiements';

    protected $fillable = [
        'uuid_public',
        'ancien_id',
        'droit_paiement_id',
        'compte_paiement_beneficiaire_id',
        'montant',
        'statut',
        'corbeille_actuelle',
        'reference_externe',
        'paye_le',
        'version_verrouillage'
    ];

    protected $casts = [
        'paye_le' => 'datetime',
        'montant' => 'decimal:2',
    ];

    public function droitPaiement(): BelongsTo
    {
        return $this->belongsTo(DroitPaiement::class);
    }

    public function dossiersPaiement(): BelongsToMany
    {
        return $this->belongsToMany(
            DossierPaiement::class, 
            'lignes_dossiers_paiement', 
            'paiement_id', 
            'dossier_paiement_id'
        )->withPivot(['montant', 'ajoute_le', 'retire_le', 'motif_retrait']);
    }

    // Scopes
    public function scopeATraiter($query)
    {
        return $query->where('statut', 'A_TRAITER');
    }
}
