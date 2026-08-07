<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isOwner();
    }

    public function update(User $user, User $target): bool
    {
        return $user->is_active && $user->isOwner();
    }
}
