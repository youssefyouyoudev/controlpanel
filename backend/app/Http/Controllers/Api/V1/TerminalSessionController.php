<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TerminalSession;
use App\Models\Website;
use App\Services\AuditLogger;
use App\Services\Terminal\TerminalSessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalSessionController extends Controller
{
    public function store(Request $request, TerminalSessionService $terminal): JsonResponse
    {
        return $this->create($request, $terminal);
    }

    public function storeForWebsite(Request $request, TerminalSessionService $terminal, Website $website): JsonResponse
    {
        return $this->create($request, $terminal, $website);
    }

    private function create(Request $request, TerminalSessionService $terminal, ?Website $website = null): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $result = $terminal->create($request->user(), $website, (string) $request->input('current_password'));
        $session = $result['session'];

        return ApiResponse::success([
            'session' => $this->payload($session),
            'ticket' => $result['ticket'],
            'websocket_url' => config('youpanel.terminal.websocket_url'),
        ], 'Terminal session ticket created.', 201);
    }

    public function show(Request $request, TerminalSession $terminalSession): JsonResponse
    {
        abort_unless($request->user()?->isOwner() && $terminalSession->user_id === $request->user()->id, 403);

        return ApiResponse::success(['session' => $this->payload($terminalSession)]);
    }

    public function destroy(Request $request, TerminalSession $terminalSession, TerminalSessionService $terminal): JsonResponse
    {
        $session = $terminal->end($terminalSession, $request->user());

        return ApiResponse::success(['session' => $this->payload($session)]);
    }

    public function validateGatewayToken(Request $request, TerminalSessionService $terminal): JsonResponse
    {
        $this->assertGatewaySecret($request);

        $data = $request->validate([
            'session' => ['required', 'string'],
            'ticket' => ['required', 'string'],
        ]);
        $session = $terminal->consumeGatewayTicket(
            (string) $data['session'],
            (string) $data['ticket'],
            $request->ip(),
            (string) $request->userAgent(),
        );

        return ApiResponse::success(['session' => $this->payload($session->refresh())]);
    }

    public function gatewayEvent(Request $request, TerminalSession $terminalSession, AuditLogger $auditLogger): JsonResponse
    {
        $this->assertGatewaySecret($request);

        $data = $request->validate([
            'event' => ['required', 'string', 'in:terminal.gateway.rejected,terminal.session.disconnected,terminal.session.idle_timeout,terminal.session.max_duration,terminal.session.output_limit'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        if (in_array($data['event'], ['terminal.session.disconnected', 'terminal.session.idle_timeout', 'terminal.session.max_duration', 'terminal.session.output_limit'], true)
            && $terminalSession->ended_at === null) {
            $terminalSession->update(['status' => 'closed', 'ended_at' => now(), 'last_activity_at' => now()]);
        }

        $auditLogger->record((string) $data['event'], $terminalSession->user, $terminalSession->website, [
            'target_type' => 'terminal_session',
            'target_identifier' => $terminalSession->uuid,
            'reason' => $data['reason'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return ApiResponse::success(['recorded' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TerminalSession $session): array
    {
        return [
            'uuid' => $session->uuid,
            'scope' => $session->scope,
            'website_id' => $session->website_id,
            'working_directory' => $session->working_directory,
            'shell' => $session->shell,
            'status' => $session->status,
            'expires_at' => $session->expires_at?->toISOString(),
            'consumed_at' => $session->consumed_at?->toISOString(),
            'idle_timeout_seconds' => $session->metadata['idle_timeout_seconds'] ?? null,
            'max_duration_seconds' => $session->metadata['max_duration_seconds'] ?? null,
        ];
    }

    private function assertGatewaySecret(Request $request): void
    {
        $configuredSecret = (string) config('youpanel.terminal.gateway_secret');
        abort_if($configuredSecret === '', 503, 'Terminal gateway secret is not configured.');
        abort_unless(hash_equals($configuredSecret, (string) $request->header('X-YouPanel-Terminal-Gateway-Secret')), 403);
    }
}
