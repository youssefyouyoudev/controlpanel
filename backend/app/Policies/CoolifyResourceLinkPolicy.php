<?php

namespace App\Policies;

use App\Models\CoolifyResourceLink;
use App\Models\User;

class CoolifyResourceLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, CoolifyResourceLink $link): bool
    {
        return $user->is_active && $link->website()->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isOwner();
    }

    public function update(User $user, CoolifyResourceLink $link): bool
    {
        return $user->is_active && $user->isOwner();
    }

    public function delete(User $user, CoolifyResourceLink $link): bool
    {
        return $user->is_active && $user->isOwner();
    }

    public function control(User $user, CoolifyResourceLink $link): bool
    {
        return $this->view($user, $link) && in_array($user->role->value, ['owner', 'developer'], true);
    }
}
