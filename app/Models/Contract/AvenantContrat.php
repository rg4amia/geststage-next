<?php

namespace App\Models\Contract;

use App\Domain\Shared\Traits\HasPublicUuid;
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

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
            'nouvelle_date_fin' => 'date',
            'nouvelle_prime_mensuelle' => 'decimal:2',
        ];
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }
}
