<?php

namespace App\Exceptions;

use RuntimeException;

class StripeException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly ?string $stripeCode = null)
    {
        parent::__construct($message);
    }
}
