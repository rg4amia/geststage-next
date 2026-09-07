<?php

namespace App\Models\Payment;

use App\Domain\Audit\Traits\Auditable;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Règle de prélèvement datée appliquée à un paiement (équivalent cible de
 * `PaymentDeductionRule`).
 *
 * Contrat repris du legacy : une règle vaut pour une source de financement, un
 * type de paiement et, optionnellement, un type de stage ; deux règles actives
 * de même portée ne peuvent pas se chevaucher dans le temps.
 */
class ReglePrelevement extends Model
{
    use Auditable;

    public const TYPE_CMU = 'CMU';

    public const PAIEMENT_DEMARRAGE = 'DEMARRAGE';

    /** Borne haute conventionnelle d'une règle sans date de fin. */
    public const FIN_OUVERTE = '9999-12-31';

    protected $table = 'regles_prelevement';

    protected $guarded = [];

    protected $casts = [
        'montant' => 'decimal:2',
        'effet_du' => 'date',
        'effet_au' => 'date',
        'actif' => 'boolean',
    ];

    /**
     * La source de financement couverte par la règle.
     */
    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    /**
     * Le type de stage couvert, ou `null` si la règle vaut pour tous les types.
     */
    public function typeStage(): BelongsTo
    {
        return $this->belongsTo(TypeStage::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }

    /**
     * Règles actives couvrant une portée donnée, hors règle en cours d'édition.
     *
     * @param  array{source_financement_id: int, type_stage_id: int|null, type_paiement: string}  $portee
     */
    public function scopeMemePortee(Builder $query, array $portee): Builder
    {
        return $query
            ->where('actif', true)
            ->where('source_financement_id', $portee['source_financement_id'])
            ->where('type_paiement', $portee['type_paiement'])
            ->when(
                $portee['type_stage_id'] === null,
                fn (Builder $sousRequete) => $sousRequete->whereNull('type_stage_id'),
                fn (Builder $sousRequete) => $sousRequete->where('type_stage_id', $portee['type_stage_id']),
            );
    }
}
