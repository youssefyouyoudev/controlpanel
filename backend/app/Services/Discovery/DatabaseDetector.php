<?php

namespace App\Services\Discovery;

use Illuminate\Support\Str;

class DatabaseDetector
{
    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, array<string, mixed>>
     */
    public function detect(?string $root, array $components = []): array
    {
        if ($root === null || ! is_dir($root)) {
            return [];
        }

        $paths = [$root, ...array_filter(array_map(fn (array $component): ?string => $component['path'] ?? null, $components))];
        $connections = [];

        foreach (array_values(array_unique($paths)) as $path) {
            $env = $this->envFile($path.DIRECTORY_SEPARATOR.'.env');
            if ($env === []) {
                continue;
            }

            $driver = $env['DB_CONNECTION'] ?? null;
            $database = $env['DB_DATABASE'] ?? null;
            if (! is_string($driver) || ! is_string($database) || $database === '') {
                continue;
            }

            $connection = [
                'driver' => strtolower($driver),
                'host' => $env['DB_HOST'] ?? null,
                'port' => isset($env['DB_PORT']) && is_numeric($env['DB_PORT']) ? (int) $env['DB_PORT'] : null,
                'database' => $database,
                'source_path' => $path.DIRECTORY_SEPARATOR.'.env',
                'source_relative_path' => $this->relativePath($root, $path.DIRECTORY_SEPARATOR.'.env'),
                'configured' => true,
            ];

            $connections[$this->key($connection)] = $connection;
        }

        return array_values($connections);
    }

    /**
     * @return array<string, string>
     */
    private function envFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 256 * 1024) {
            return [];
        }

        $safeKeys = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE'];
        $env = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (! in_array($key, $safeKeys, true)) {
                continue;
            }

            $env[$key] = trim(trim($value), "\"'");
        }

        return $env;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function key(array $connection): string
    {
        return implode('|', [
            $connection['driver'] ?? '',
            $connection['host'] ?? '',
            $connection['port'] ?? '',
            $connection['database'] ?? '',
        ]);
    }

    private function relativePath(string $projectRoot, string $path): string
    {
        $relative = Str::after(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $projectRoot), '/').'/');

        return $relative === str_replace('\\', '/', $path) ? basename($path) : $relative;
    }
}
