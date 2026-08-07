<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebsiteLogSource;

class WebsiteLogSourcePolicy
{
    public function view(User $user, WebsiteLogSource $source): bool
    {
        return $user->can('view', $source->website);
    }

    public function update(User $user, WebsiteLogSource $source): bool
    {
        return $user->is_active && $user->isOwner();
    }
}
