<?php

namespace App\Domain\Payment\Services\Prime;

use RuntimeException;
use Throwable;

/**
 * Le barème ne couvre pas la situation du stage : la prime ne peut pas être
 * établie. Portage de `App\Exceptions\PrimeCalculationException` (legacy).
 */
class PrimeCalculationException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        ?Throwable $previous = null,
        public readonly array $context = [],
    ) {
        parent::__construct($message, 0, $previous);
    }
}
