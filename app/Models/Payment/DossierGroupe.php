<?php

namespace App\Models\Payment;

use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DossierGroupe extends Model
{
    protected $table = 'dossiers_groupes';

    protected $fillable = [
        'uuid_public', 'ancien_id', 'periode_id', 'source_financement_id',
        'cree_par_id', 'numero', 'nature', 'statut', 'montant_total', 'observation',
    ];

    protected $casts = ['montant_total' => 'decimal:2'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }

    public function dossiers(): BelongsToMany
    {
        return $this->belongsToMany(
            DossierPaiement::class,
            'lignes_dossiers_groupes',
            'dossier_groupe_id',
            'dossier_paiement_id',
        )->withPivot(['ajoute_le', 'retire_le', 'motif_retrait'])->wherePivotNull('retire_le');
    }
}
