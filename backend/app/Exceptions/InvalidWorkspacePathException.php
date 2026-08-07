<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidWorkspacePathException extends RuntimeException
{
    public static function forUser(string $message = 'This folder is outside the approved workspace.'): self
    {
        return new self($message);
    }
}
