<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebsiteComponent;

class WebsiteComponentPolicy
{
    public function view(User $user, WebsiteComponent $component): bool
    {
        return $user->can('view', $component->website);
    }

    public function update(User $user, WebsiteComponent $component): bool
    {
        return $user->is_active && $user->isOwner();
    }
}
