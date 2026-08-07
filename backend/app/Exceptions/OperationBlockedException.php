<?php

namespace App\Exceptions;

use RuntimeException;

class OperationBlockedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
