<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->is_active && AuditLog::query()->whereKey($auditLog->id)->visibleTo($user)->exists();
    }
}
