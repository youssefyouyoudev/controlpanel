<?php

namespace App\Services\Discovery;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class GitInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(?string $root): array
    {
        if ($root === null || ! is_dir($root.DIRECTORY_SEPARATOR.'.git')) {
            return [];
        }

        $commit = $this->run(['git', 'log', '-1', '--pretty=%H%x1f%s%x1f%cI'], $root);
        $parts = explode("\x1f", trim($commit));
        $changes = array_values(array_filter(explode("\n", trim($this->run(['git', 'status', '--short'], $root)))));
        $remote = $this->redactRemote($this->run(['git', 'remote', 'get-url', 'origin'], $root));

        return [
            'remote_url' => $remote,
            'provider' => $this->provider($remote),
            'repository' => $this->repository($remote),
            'branch' => trim($this->run(['git', 'branch', '--show-current'], $root)) ?: null,
            'last_commit' => [
                'hash' => $parts[0] ?? null,
                'short_hash' => isset($parts[0]) ? substr($parts[0], 0, 8) : null,
                'message' => $parts[1] ?? null,
                'date' => $parts[2] ?? null,
            ],
            'dirty' => $changes !== [],
        ];
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command, string $workingDirectory): string
    {
        if (! $this->binaryExists($command[0])) {
            return '';
        }

        $process = new Process($command, $workingDirectory);
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

    private function redactRemote(string $remote): ?string
    {
        $remote = trim($remote);
        if ($remote === '') {
            return null;
        }

        return preg_replace('/\/\/[^@\s]+@/', '//[redacted]@', $remote) ?: $remote;
    }

    private function provider(?string $remote): ?string
    {
        if (! $remote) {
            return null;
        }

        return match (true) {
            str_contains($remote, 'github.com') => 'github',
            str_contains($remote, 'gitlab.com') => 'gitlab',
            str_contains($remote, 'bitbucket.org') => 'bitbucket',
            default => parse_url($remote, PHP_URL_HOST) ?: null,
        };
    }

    private function repository(?string $remote): ?string
    {
        if (! $remote) {
            return null;
        }

        $path = parse_url($remote, PHP_URL_PATH);
        if (! is_string($path)) {
            $path = Str::after($remote, ':');
        }

        return trim(preg_replace('/\.git$/', '', $path) ?: $path, '/');
    }
}
