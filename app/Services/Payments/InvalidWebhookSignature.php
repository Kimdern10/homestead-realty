<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Thrown when a webhook's signature doesn't match, so it can't be trusted.
 */
class InvalidWebhookSignature extends RuntimeException
{
    //
}
