<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LigneDossierPaiement extends Pivot
{
    use HasFactory;

    protected $table = 'lignes_dossiers_paiement';

    protected $fillable = [
        'dossier_paiement_id',
        'paiement_id',
        'montant',
        'ajoute_le',
        'retire_le',
        'motif_retrait'
    ];

    protected $casts = [
        'ajoute_le' => 'datetime',
        'retire_le' => 'datetime',
        'montant' => 'decimal:2',
    ];
}
