<?php

namespace App\Policies;

use App\Models\Deployment;
use App\Models\User;
use App\Models\Website;

class DeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Deployment $deployment): bool
    {
        return $user->is_active && $deployment->website()->visibleTo($user)->exists();
    }

    public function create(User $user, Website $website): bool
    {
        return $user->is_active && Website::query()->whereKey($website->id)->visibleTo($user)->exists() && in_array($user->role->value, ['owner', 'developer'], true);
    }

    public function approve(User $user, Deployment $deployment): bool
    {
        return $user->is_active && $user->isOwner();
    }

    public function cancel(User $user, Deployment $deployment): bool
    {
        return $this->view($user, $deployment) && ($user->isOwner() || $deployment->requested_by === $user->id);
    }

    public function viewLogs(User $user, Deployment $deployment): bool
    {
        return $this->view($user, $deployment) && in_array($user->role->value, ['owner', 'developer', 'editor'], true);
    }
}
