<?php

namespace App\Services\Operations;

use App\Exceptions\OperationBlockedException;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteLogSource;

class LogReaderService
{
    public function __construct(private readonly SecretRedactor $redactor) {}

    /**
     * @return array<string, mixed>
     */
    public function read(Website $website, User $user, WebsiteLogSource $source, int $lines, ?string $search = null, ?string $level = null): array
    {
        $this->assertSource($website, $user, $source);
        $lineLimit = min((int) config('youpanel.logs.max_lines'), max(1, $lines));
        $content = $this->tail($source, $lineLimit);
        $rows = array_map(fn (string $line): string => $this->redactor->redact($line), $content);

        if ($search) {
            $rows = array_values(array_filter($rows, fn (string $line): bool => str_contains(strtolower($line), strtolower($search))));
        }

        if ($level) {
            $rows = array_values(array_filter($rows, fn (string $line): bool => str_contains(strtolower($line), strtolower($level))));
        }

        return [
            'source' => $source->slug,
            'lines' => array_map(fn (string $line, int $index): array => [
                'number' => $index + 1,
                'level' => $this->detectLevel($line),
                'text' => $line,
            ], $rows, array_keys($rows)),
            'redacted' => true,
        ];
    }

    private function assertSource(Website $website, User $user, WebsiteLogSource $source): void
    {
        if ($source->website_id !== $website->id || ! $source->is_active || ! $user->can('view', $website)) {
            throw new OperationBlockedException('This log source is not available.');
        }

        if ((bool) config('youpanel-actions.mock')) {
            return;
        }

        $path = $source->absolute_path;
        if (! $path || ! is_file($path) || ! is_readable($path)) {
            throw new OperationBlockedException('This log source is unavailable.');
        }

        if (filesize($path) > (int) config('youpanel.logs.download_max_bytes') && ! $user->isOwner()) {
            throw new OperationBlockedException('This log file is too large for non-owner access.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function tail(WebsiteLogSource $source, int $lines): array
    {
        if ((bool) config('youpanel-actions.mock')) {
            return [
                now()->toISOString().' INFO Mock log source connected',
                now()->toISOString().' WARNING Example warning with token=[redacted]',
                now()->toISOString().' ERROR Example failed build line',
            ];
        }

        $file = new \SplFileObject((string) $source->absolute_path);
        $file->seek(PHP_INT_MAX);
        $last = $file->key();
        $start = max(0, $last - $lines);
        $rows = [];
        for ($i = $start; $i <= $last; $i++) {
            $file->seek($i);
            $rows[] = rtrim((string) $file->current(), "\r\n");
        }

        return array_values(array_filter($rows, fn (string $line): bool => $line !== ''));
    }

    private function detectLevel(string $line): string
    {
        return match (true) {
            str_contains(strtolower($line), 'error') => 'error',
            str_contains(strtolower($line), 'warn') => 'warning',
            str_contains(strtolower($line), 'debug') => 'debug',
            default => 'info',
        };
    }
}
