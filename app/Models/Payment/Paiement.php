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
        'montant_brut',
        'montant_prelevement',
        'type_prelevement',
        'regle_prelevement_id',
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
        'montant_brut' => 'decimal:2',
        'montant_prelevement' => 'decimal:2',
        'dossier_physique_marque_le' => 'datetime',
    ];

    /**
     * Montant brut avant prélèvement (CMU) : vaut le montant historique quand
     * aucun prélèvement n'a été appliqué au paiement.
     */
    public function getMontantBrutCalculeAttribute(): float
    {
        return (float) ($this->montant_brut ?? $this->montant ?? 0);
    }

    /**
     * La part prélevée (cotisation CMU) sur ce paiement, zéro si aucune règle
     * ne s'applique.
     */
    public function getPrelevementCalculeAttribute(): float
    {
        return (float) ($this->montant_prelevement ?? 0);
    }

    /**
     * Un prélèvement a-t-il été opéré sur ce paiement ? Sert aux badges CB/AC.
     */
    public function getAPrelevementAttribute(): bool
    {
        return $this->prelevement_calcule > 0.009;
    }

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

    /**
     * La règle de prélèvement (CMU) appliquée à ce paiement, si applicable.
     */
    public function reglePrelevement(): BelongsTo
    {
        return $this->belongsTo(ReglePrelevement::class);
    }

    // Scopes
    public function scopeATraiter($query)
    {
        return $query->where('statut', 'A_TRAITER');
    }
}
