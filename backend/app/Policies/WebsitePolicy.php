<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function viewDashboard(User $user): bool
    {
        return $user->is_active;
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Website $website): bool
    {
        return $user->is_active && Website::query()->whereKey($website->id)->visibleTo($user)->exists();
    }

    public function update(User $user, Website $website): bool
    {
        return $this->view($user, $website) && $user->role->canModifyAssignedWebsite();
    }
}
