<?php

namespace App\Models\Payment;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Payment\Services\Prime\PrimeConfigurationService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surcharge administrateur du barème des primes (ligne unique id = 1).
 *
 * @see PrimeConfigurationService
 */
class PrimeConfigurationOverride extends Model
{
    use Auditable;

    protected $table = 'prime_configuration_overrides';

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
    ];

    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }
}
