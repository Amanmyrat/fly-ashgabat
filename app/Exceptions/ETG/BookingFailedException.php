<?php

namespace App\Exceptions\ETG;

use RuntimeException;

/**
 * Thrown when ETG rejects or fails to confirm a booking order.
 *
 * @phpstan-type ErrorCode 'PREBOOK_FAILED'|'BOOKING_FAILED'|'BALANCE_AUTH_REQUIRED'|'INSUFFICIENT_BALANCE'
 */
class BookingFailedException extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly string $errorCode = 'BOOKING_FAILED',
    ) {
        parent::__construct($message);
    }
}
