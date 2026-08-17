<?php

namespace App\Models\Offer;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Company\Entreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffreEmploi extends Model
{
    use Auditable, HasPublicUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'offres_emploi';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * L'entreprise proposant l'offre.
     */
    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }
}
