<?php

namespace App\Services\Operations;

use App\Exceptions\OperationBlockedException;
use Symfony\Component\Process\Process;

class GitService
{
    public function __construct(private readonly SecretRedactor $redactor) {}

    /**
     * @return array<string, mixed>
     */
    public function status(string $workingDirectory): array
    {
        $this->assertRepository($workingDirectory);
        $branch = trim($this->run(['git', 'branch', '--show-current'], $workingDirectory));
        $commit = trim($this->run(['git', 'log', '-1', '--pretty=%h %s'], $workingDirectory));
        $remote = $this->safeRemote(trim($this->run(['git', 'remote', 'get-url', 'origin'], $workingDirectory, allowFailure: true)));
        $changes = array_values(array_filter(explode("\n", trim($this->run(['git', 'status', '--short'], $workingDirectory)))));

        return [
            'branch' => $branch ?: 'unknown',
            'latest_commit' => $commit ?: null,
            'remote_url' => $remote ?: null,
            'dirty' => $changes !== [],
            'changes' => $changes,
            'ahead' => null,
            'behind' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function commits(string $workingDirectory, int $limit = 10): array
    {
        $this->assertRepository($workingDirectory);
        $output = $this->run(['git', 'log', '--pretty=%h %s', '-n', (string) min(25, max(1, $limit))], $workingDirectory);

        return array_values(array_filter(explode("\n", trim($output))));
    }

    /**
     * @return array<int, string>
     */
    public function branches(string $workingDirectory): array
    {
        $this->assertRepository($workingDirectory);
        $output = $this->run(['git', 'branch', '--list', '--format=%(refname:short)'], $workingDirectory);

        return array_values(array_filter(explode("\n", trim($output))));
    }

    public function assertPullSafe(string $workingDirectory): void
    {
        $status = $this->status($workingDirectory);
        if ($status['dirty']) {
            throw new OperationBlockedException('Git pull is blocked because local files have changes.', ['changes' => $status['changes']]);
        }
    }

    private function assertRepository(string $workingDirectory): void
    {
        if (! is_dir($workingDirectory.DIRECTORY_SEPARATOR.'.git')) {
            throw new OperationBlockedException('This component is not a Git repository.');
        }
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command, string $workingDirectory, bool $allowFailure = false): string
    {
        if ((bool) config('youpanel-actions.mock')) {
            return match ($command[1] ?? '') {
                'branch' => 'main',
                'log' => 'abc123 Mock commit',
                'remote' => 'https://github.com/example/private.git',
                'status' => '',
                default => '',
            };
        }

        $process = new Process($command, $workingDirectory, null, null, 20);
        $process->run();
        if (! $allowFailure && ! $process->isSuccessful()) {
            throw new OperationBlockedException('Git command failed safely.');
        }

        return $this->redactor->redact($process->getOutput().$process->getErrorOutput());
    }

    private function safeRemote(string $remote): string
    {
        return preg_replace('/\/\/[^@\s]+@/', '//[redacted]@', $remote) ?? $remote;
    }
}
