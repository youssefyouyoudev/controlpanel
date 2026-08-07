<?php

namespace App\Exceptions;

use RuntimeException;

class WorkspacePermissionException extends RuntimeException
{
    public static function denied(string $message = 'You do not have permission for this file operation.'): self
    {
        return new self($message);
    }
}
