<?php

namespace App\Models\Contract;

use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avenant à un contrat de stage.
 *
 * Porte notamment les renouvellements repris du legacy (`contrats_pae.etatrenouvellement_id`
 * avec `date_debut_renouv` / `date_fin_renouv`), qui arbitrent le partage des corbeilles de
 * paiement DMG entre « démarrage » et « présence ».
 */
class AvenantContrat extends Model
{
    use HasFactory, HasPublicUuid;

    protected $table = 'avenants_contrats';

    protected $guarded = [];

    /** Renouvellement proposé par le CIP, en attente de la décision du chef d'agence. */
    public const STATUT_ATTENTE_CA = 'ATTENTE_CA';

    /** Renouvellement accepté : c'est l'état des avenants repris du legacy. */
    public const STATUT_VALIDE = 'VALIDE';

    /** Renouvellement ajourné par le chef d'agence, à corriger par le CIP. */
    public const STATUT_AJOURNE = 'AJOURNE';

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
            'nouvelle_date_fin' => 'date',
            'nouvelle_prime_mensuelle' => 'decimal:2',
            'decide_le' => 'datetime',
            'propose_le' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function typeStructure(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Reference\TypeStructure::class, 'type_structure_id');
    }

    public function proposePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propose_par_id');
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decideur_id');
    }
}
