<?php

namespace App\Models\Payment;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paiement extends Model
{
    use Auditable, HasFactory, HasPublicUuid;

    protected $table = 'paiements';

    protected $fillable = [
        'uuid_public',
        'ancien_id',
        'droit_paiement_id',
        'compte_paiement_beneficiaire_id',
        'montant',
        'statut',
        'corbeille_actuelle',
        'statut_dossier_physique',
        'dossier_physique_marque_par_id',
        'dossier_physique_marque_le',
        'reference_externe',
        'paye_le',
        'version_verrouillage',
    ];

    protected $casts = [
        'paye_le' => 'datetime',
        'montant' => 'decimal:2',
        'dossier_physique_marque_le' => 'datetime',
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

    public function decisions(): HasMany
    {
        return $this->hasMany(DecisionPaiement::class);
    }

    // Scopes
    public function scopeATraiter($query)
    {
        return $query->where('statut', 'A_TRAITER');
    }
}
