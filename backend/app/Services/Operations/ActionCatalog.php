<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Exceptions\OperationBlockedException;
use App\Models\User;
use App\Models\WebsiteComponent;

class ActionCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        $actions = config('youpanel-actions.actions', []);
        $action = is_array($actions) ? ($actions[$key] ?? null) : null;
        if (! is_array($action)) {
            throw new OperationBlockedException('This action is not in the YouPanel allowlist.');
        }

        return $this->normalize(['key' => $key, ...$action]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return collect(config('youpanel-actions.actions', []))
            ->map(fn (array $definition, string $key): array => $this->normalize(['key' => $key, ...$definition]))
            ->values()
            ->all();
    }

    public function assertCanRun(User $user, array $definition, ?WebsiteComponent $component = null): void
    {
        if (! ($definition['enabled'] ?? false)) {
            throw new OperationBlockedException('This action is disabled until a safe production runner is configured.');
        }

        $required = UserRole::from((string) $definition['required_role']);
        $allowed = match ($required) {
            UserRole::Owner => $user->isOwner(),
            UserRole::Developer => in_array($user->role, [UserRole::Owner, UserRole::Developer], true),
            UserRole::Editor => in_array($user->role, [UserRole::Owner, UserRole::Developer, UserRole::Editor], true),
            UserRole::Viewer => $user->is_active,
        };

        if (! $allowed) {
            throw new OperationBlockedException('Your role cannot run this action.');
        }

        if ($component) {
            $types = $definition['component_types'] ?? [];
            if ($types !== [] && ! in_array($component->type->value, $types, true)) {
                throw new OperationBlockedException('This action does not apply to the selected component.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function normalize(array $definition): array
    {
        if (in_array(($definition['risk_level'] ?? 'low'), ['medium', 'high'], true)) {
            $definition['requires_confirmation'] = true;
        }

        if (in_array($definition['key'], ['laravel.migrate', 'laravel.maintenance_enable', 'website.restore_backup'], true)) {
            $definition['requires_password_confirmation'] = true;
        }

        if ($definition['key'] === 'laravel.migrate') {
            $definition['backup_required'] = true;
        }

        if (($definition['executor'] ?? null) === 'disabled' || str_starts_with((string) $definition['key'], 'service.')) {
            $definition['enabled'] = false;
        }

        return $definition;
    }
}
