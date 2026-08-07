<?php

namespace App\Policies;

use App\Models\ConsoleExecution;
use App\Models\User;
use App\Models\Website;

class ConsoleExecutionPolicy
{
    public function view(User $user, ConsoleExecution $execution): bool
    {
        return $user->is_active && $execution->website()->visibleTo($user)->exists();
    }

    public function create(User $user, Website $website): bool
    {
        return $user->is_active && Website::query()->whereKey($website->id)->visibleTo($user)->exists() && in_array($user->role->value, ['owner', 'developer'], true);
    }

    public function cancel(User $user, ConsoleExecution $execution): bool
    {
        return $this->view($user, $execution) && ($user->isOwner() || $execution->requested_by === $user->id);
    }
}
