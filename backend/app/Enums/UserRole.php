<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Developer = 'developer';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canModifyAssignedWebsite(): bool
    {
        return in_array($this, [self::Owner, self::Developer, self::Editor], true);
    }

    public function canManageGlobalSettings(): bool
    {
        return $this === self::Owner;
    }
}
