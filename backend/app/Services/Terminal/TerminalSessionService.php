<?php

namespace App\Services\Terminal;

use App\Exceptions\OperationBlockedException;
use App\Models\TerminalSession;
use App\Models\User;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TerminalSessionService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array{session: TerminalSession, token: string}
     */
    public function create(User $user, ?Website $website, string $currentPassword): array
    {
        if (! (bool) config('youpanel.terminal.enabled')) {
            throw new OperationBlockedException('The terminal is disabled.');
        }

        if (! $user->isOwner()) {
            throw new OperationBlockedException('Only owners may open terminal sessions.');
        }

        if (! Hash::check($currentPassword, $user->password)) {
            throw new OperationBlockedException('Recent authentication failed.');
        }

        if ($website && ! $user->can('view', $website)) {
            throw new OperationBlockedException('You are not authorized for this website.');
        }

        $this->assertConcurrentLimit($user);
        $workingDirectory = $website ? $this->websiteWorkingDirectory($website) : $this->homeDirectory();
        $token = Str::random(64);

        $session = TerminalSession::query()->create([
            'user_id' => $user->id,
            'website_id' => $website?->id,
            'scope' => $website ? 'website' : 'server',
            'token_hash' => hash('sha256', $token),
            'working_directory' => $workingDirectory,
            'shell' => (string) config('youpanel.terminal.shell'),
            'status' => 'pending',
            'expires_at' => now()->addSeconds((int) config('youpanel.terminal.token_ttl_seconds')),
            'metadata' => [
                'idle_timeout_seconds' => (int) config('youpanel.terminal.idle_timeout_seconds'),
                'max_duration_seconds' => (int) config('youpanel.terminal.max_duration_seconds'),
            ],
        ]);

        $this->auditLogger->record('terminal.session.created', $user, $website, [
            'target_type' => 'terminal_session',
            'target_identifier' => $session->uuid,
            'scope' => $session->scope,
        ]);

        return ['session' => $session, 'token' => $token];
    }

    public function validateToken(string $uuid, string $token): TerminalSession
    {
        $session = TerminalSession::query()->where('uuid', $uuid)->firstOrFail();

        if (! hash_equals($session->token_hash, hash('sha256', $token)) || $session->expired() || $session->ended_at !== null) {
            throw new OperationBlockedException('The terminal session token is invalid or expired.');
        }

        return $session;
    }

    public function end(TerminalSession $session, User $user): TerminalSession
    {
        if (! $user->isOwner() || $session->user_id !== $user->id) {
            throw new OperationBlockedException('You are not authorized for this terminal session.');
        }

        $session->update(['status' => 'closed', 'ended_at' => now()]);
        $this->auditLogger->record('terminal.session.closed', $user, $session->website, [
            'target_type' => 'terminal_session',
            'target_identifier' => $session->uuid,
        ]);

        return $session->refresh();
    }

    private function assertConcurrentLimit(User $user): void
    {
        $active = TerminalSession::query()
            ->whereBelongsTo($user)
            ->whereNull('ended_at')
            ->where('expires_at', '>', now())
            ->count();

        if ($active >= (int) config('youpanel.terminal.max_concurrent_per_user')) {
            throw new OperationBlockedException('Too many terminal sessions are already active for this user.');
        }
    }

    private function homeDirectory(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: base_path();
        $real = realpath($home);

        return $real && is_dir($real) ? $real : base_path();
    }

    private function websiteWorkingDirectory(Website $website): string
    {
        $root = $website->allowedPaths()
            ->where('is_active', true)
            ->where('is_primary', true)
            ->orderBy('id')
            ->first()
            ?->absolute_path ?? $website->root_path;

        $real = realpath($root);
        if ($real === false || ! is_dir($real) || ! is_readable($real)) {
            throw new OperationBlockedException('This website does not have a readable approved terminal working directory.');
        }

        $approved = $website->allowedPaths()
            ->where('is_active', true)
            ->get()
            ->contains(function ($allowedPath) use ($real): bool {
                $allowedRoot = realpath($allowedPath->absolute_path);

                return $allowedRoot !== false && ($real === $allowedRoot || str_starts_with($real, rtrim($allowedRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR));
            });

        if (! $approved) {
            throw new OperationBlockedException('The website terminal directory is not inside an approved file root.');
        }

        return $real;
    }
}
