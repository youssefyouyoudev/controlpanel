<?php

namespace App\Exceptions;

use RuntimeException;

class CoolifyRateLimitException extends RuntimeException
{
    public function __construct(string $message = 'Coolify rate limit reached.', public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
