<?php

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;

class BackupPolicy
{
    public function view(User $user, Backup $backup): bool
    {
        return $user->can('view', $backup->website);
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role->value, ['owner', 'developer'], true);
    }

    public function restore(User $user, Backup $backup): bool
    {
        return $user->is_active && $user->isOwner();
    }
}
