<?php

namespace App\Console\Commands;

use App\Models\AllowedPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PermissionAuditCommand extends Command
{
    protected $signature = 'youpanel:permission-audit {--json : Output machine-readable JSON}';

    protected $description = 'Inspect filesystem permission signals for YouPanel without modifying files.';

    public function handle(): int
    {
        $items = [
            $this->inspectPath('storage', storage_path()),
            $this->inspectPath('storage/logs', storage_path('logs')),
            $this->inspectPath('storage/framework', storage_path('framework')),
            $this->inspectPath('bootstrap/cache', base_path('bootstrap/cache')),
        ];

        if (Schema::hasTable('allowed_paths')) {
            AllowedPath::query()
                ->select(['id', 'name', 'absolute_path', 'can_write', 'can_delete'])
                ->orderBy('id')
                ->get()
                ->each(function (AllowedPath $path) use (&$items): void {
                    $items[] = [
                        ...$this->inspectPath('allowed_path:'.$path->id.':'.$path->name, $path->absolute_path),
                        'write_enabled' => (bool) $path->can_write,
                        'delete_enabled' => (bool) $path->can_delete,
                    ];
                });
        }

        if ($this->option('json')) {
            $this->line(json_encode(['items' => $items, 'checked_at' => now()->toISOString()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach ($items as $item) {
                $level = $item['exists'] && ! $item['world_writable'] ? 'info' : 'warn';
                $this->{$level}(sprintf(
                    '[%s] %s exists=%s readable=%s writable=%s world_writable=%s',
                    $level === 'info' ? 'OK' : 'REVIEW',
                    $item['label'],
                    $item['exists'] ? 'yes' : 'no',
                    $item['readable'] ? 'yes' : 'no',
                    $item['writable'] ? 'yes' : 'no',
                    $item['world_writable'] ? 'yes' : 'no',
                ));
            }
        }

        return collect($items)->contains(fn (array $item): bool => ! $item['exists'] || $item['world_writable'])
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @return array{label: string, path: string, exists: bool, readable: bool, writable: bool, world_writable: bool, permissions: string|null}
     */
    private function inspectPath(string $label, string $path): array
    {
        $exists = file_exists($path);
        $permissions = $exists ? substr(sprintf('%o', fileperms($path)), -4) : null;

        return [
            'label' => $label,
            'path' => $path,
            'exists' => $exists,
            'readable' => $exists && is_readable($path),
            'writable' => $exists && is_writable($path),
            'world_writable' => DIRECTORY_SEPARATOR === '/' && $permissions !== null && str_ends_with($permissions, '7'),
            'permissions' => $permissions,
        ];
    }
}
