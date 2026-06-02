<?php

namespace App\Exceptions\ETG;

use RuntimeException;

/**
 * Thrown when ETG returns a different price between the prebook and book steps.
 * The caller should surface this to the user so they can confirm the new price.
 */
class PriceChangedException extends RuntimeException
{
    public function __construct(
        public readonly float  $originalAmount,
        public readonly float  $newAmount,
        public readonly string $currency,
    ) {
        parent::__construct(
            "Hotel price changed from {$currency} {$originalAmount} to {$currency} {$newAmount}. Please re-confirm."
        );
    }
}
