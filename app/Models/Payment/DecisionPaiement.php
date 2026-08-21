<?php

namespace App\Models\Payment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionPaiement extends Model
{
    protected $fillable = [
        'paiement_id', 'auteur_id', 'decision', 'statut_avant',
        'statut_apres', 'motif', 'decide_le',
    ];

    protected $casts = ['decide_le' => 'datetime'];

    public static function enregistrer(
        Paiement $paiement,
        User $auteur,
        string $decision,
        ?string $motif = null,
        ?string $statutAvant = null,
        ?string $statutApres = null,
    ): self {
        return self::create([
            'paiement_id' => $paiement->id,
            'auteur_id' => $auteur->id,
            'decision' => $decision,
            'statut_avant' => $statutAvant,
            'statut_apres' => $statutApres,
            'motif' => $motif,
            'decide_le' => now(),
        ]);
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
