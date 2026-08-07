<?php

namespace App\Policies;

use App\Models\ActionExecution;
use App\Models\User;

class ActionExecutionPolicy
{
    public function view(User $user, ActionExecution $execution): bool
    {
        return $user->can('view', $execution->website);
    }

    public function update(User $user, ActionExecution $execution): bool
    {
        return $user->can('view', $execution->website) && in_array($user->role->value, ['owner', 'developer'], true);
    }
}
