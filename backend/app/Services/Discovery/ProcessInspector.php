<?php

namespace App\Services\Discovery;

use Symfony\Component\Process\Process;

class ProcessInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(?string $root, ?string $proxyPass = null): array
    {
        return [
            'pm2' => $this->pm2Processes($root, $proxyPass),
            'php_fpm' => $this->phpFpm(),
            'docker' => $this->docker($root),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pm2Processes(?string $root, ?string $proxyPass): array
    {
        if (! $this->binaryExists('pm2')) {
            return [];
        }

        $payload = json_decode($this->run(['pm2', 'jlist']), true);
        if (! is_array($payload)) {
            return [];
        }

        $proxyPort = $this->portFromProxy($proxyPass);
        $root = $root ? $this->normalize($root) : null;
        $matches = [];

        foreach ($payload as $process) {
            if (! is_array($process)) {
                continue;
            }

            $cwd = data_get($process, 'pm2_env.pm_cwd');
            $args = json_encode([data_get($process, 'pm2_env.args'), data_get($process, 'pm2_env.PORT'), data_get($process, 'pm2_env.env.PORT')]) ?: '';
            $matchesRoot = is_string($cwd) && $root && str_starts_with($this->normalize($cwd), $root);
            $matchesPort = $proxyPort !== null && str_contains($args, (string) $proxyPort);

            if (! $matchesRoot && ! $matchesPort) {
                continue;
            }

            $matches[] = [
                'name' => $process['name'] ?? null,
                'status' => data_get($process, 'pm2_env.status'),
                'pid' => $process['pid'] ?? null,
                'cwd' => $cwd,
                'restarts' => data_get($process, 'pm2_env.restart_time'),
                'uptime' => data_get($process, 'pm2_env.pm_uptime'),
                'cpu' => data_get($process, 'monit.cpu'),
                'memory' => data_get($process, 'monit.memory'),
            ];
        }

        return $matches;
    }

    /**
     * @return array<string, mixed>
     */
    private function phpFpm(): array
    {
        if (! $this->binaryExists('systemctl')) {
            return ['available' => false, 'status' => 'unknown', 'unit' => null];
        }

        foreach (['php8.3-fpm.service', 'php8.2-fpm.service', 'php8.1-fpm.service', 'php-fpm.service'] as $unit) {
            $status = trim($this->run(['systemctl', 'is-active', $unit]));
            if ($status !== '' && $status !== 'unknown') {
                return ['available' => true, 'status' => $status, 'unit' => $unit];
            }
        }

        return ['available' => false, 'status' => 'unknown', 'unit' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function docker(?string $root): array
    {
        return [
            'compose_file' => $root && (is_file($root.DIRECTORY_SEPARATOR.'docker-compose.yml') || is_file($root.DIRECTORY_SEPARATOR.'docker-compose.yaml')),
            'available' => $this->binaryExists('docker'),
        ];
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout(5);
        $process->run();

        return $process->getOutput().$process->getErrorOutput();
    }

    private function binaryExists(string $binary): bool
    {
        $paths = array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH')));
        $extensions = PHP_OS_FAMILY === 'Windows'
            ? array_filter(explode(';', (string) getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD'))
            : [''];

        foreach (array_unique([...$paths, '/usr/local/sbin', '/usr/local/bin', '/usr/sbin', '/usr/bin', '/sbin', '/bin']) as $path) {
            foreach ($extensions as $extension) {
                if (is_executable(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary.$extension)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function portFromProxy(?string $proxyPass): ?int
    {
        $port = $proxyPass ? parse_url($proxyPass, PHP_URL_PORT) : null;

        return is_int($port) ? $port : null;
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', rtrim(realpath($path) ?: $path, DIRECTORY_SEPARATOR));
    }
}
