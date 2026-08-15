<?php

namespace App\Models\Payment;

use App\Domain\Shared\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    use HasFactory, HasPublicUuid;

    protected $table = 'paiements';

    protected $guarded = [];

    protected $casts = [
        'paye_le' => 'datetime',
    ];

    /**
     * Le droit de paiement justifiant ce décaissement.
     */
    public function droitPaiement(): BelongsTo
    {
        return $this->belongsTo(DroitPaiement::class);
    }
}
