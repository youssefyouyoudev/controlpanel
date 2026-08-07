<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Server $server): bool
    {
        return $user->is_active && ($user->isOwner() || $server->websites()->visibleTo($user)->exists());
    }

    public function update(User $user, Server $server): bool
    {
        return $user->is_active && $user->isOwner();
    }
}
