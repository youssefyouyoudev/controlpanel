<?php

namespace App\Services\Terminal;

use App\Exceptions\OperationBlockedException;
use App\Models\TerminalSession;
use App\Models\User;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TerminalSessionService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array{session: TerminalSession, ticket: string}
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
        $ticket = Str::random(80);
        $ttl = max(5, min(60, (int) config('youpanel.terminal.token_ttl_seconds', 60)));

        $session = TerminalSession::query()->create([
            'user_id' => $user->id,
            'website_id' => $website?->id,
            'scope' => $website ? 'website' : 'server',
            'token_hash' => hash('sha256', $ticket),
            'working_directory' => $workingDirectory,
            'shell' => (string) config('youpanel.terminal.shell'),
            'status' => 'pending',
            'expires_at' => now()->addSeconds($ttl),
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

        return ['session' => $session, 'ticket' => $ticket];
    }

    public function consumeGatewayTicket(string $uuid, string $ticket, ?string $ipAddress = null, ?string $userAgent = null): TerminalSession
    {
        if (! Str::isUuid($uuid) || ! is_string($ticket) || strlen($ticket) < 32 || strlen($ticket) > 256) {
            $this->auditLogger->record('terminal.gateway.rejected', null, null, [
                'reason' => 'malformed_ticket',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            throw new OperationBlockedException('The terminal session token is invalid or expired.');
        }

        $ticketHash = hash('sha256', $ticket);
        $session = TerminalSession::query()->where('uuid', $uuid)->first();

        if (! $session) {
            $this->auditLogger->record('terminal.gateway.rejected', null, null, [
                'reason' => 'unknown_session',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            throw (new ModelNotFoundException)->setModel(TerminalSession::class, [$uuid]);
        }

        if (! $session->user?->isOwner()) {
            $this->auditLogger->record('terminal.gateway.rejected', $session->user, $session->website, [
                'target_type' => 'terminal_session',
                'target_identifier' => $session->uuid,
                'reason' => 'non_owner_session',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            throw new OperationBlockedException('The terminal session token is invalid or expired.');
        }

        $updated = DB::transaction(function () use ($session, $ticketHash): int {
            return TerminalSession::query()
                ->whereKey($session->id)
                ->where('token_hash', $ticketHash)
                ->whereNull('consumed_at')
                ->whereNull('ended_at')
                ->where('expires_at', '>', now())
                ->update([
                    'consumed_at' => now(),
                    'status' => 'running',
                    'started_at' => $session->started_at ?? now(),
                    'last_activity_at' => now(),
                ]);
        });

        if ($updated === 1) {
            $session = $session->refresh();
            $this->auditLogger->record('terminal.gateway.accepted', $session->user, $session->website, [
                'target_type' => 'terminal_session',
                'target_identifier' => $session->uuid,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $session;
        }

        $session->refresh();
        $reason = match (true) {
            ! hash_equals($session->token_hash, $ticketHash) => 'invalid_ticket',
            $session->consumed_at !== null => 'replay',
            $session->ended_at !== null => 'ended_session',
            $session->expired() => 'expired_ticket',
            default => 'consume_failed',
        };

        $this->auditLogger->record($reason === 'replay' ? 'terminal.gateway.replay' : 'terminal.gateway.rejected', $session->user, $session->website, [
            'target_type' => 'terminal_session',
            'target_identifier' => $session->uuid,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        throw new OperationBlockedException('The terminal session token is invalid or expired.');
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
