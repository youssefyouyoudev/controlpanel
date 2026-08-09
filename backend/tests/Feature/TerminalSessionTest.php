<?php

use App\Exceptions\OperationBlockedException;
use App\Models\AllowedPath;
use App\Models\AuditLog;
use App\Models\TerminalSession;
use App\Models\User;
use App\Models\Website;
use App\Services\Terminal\TerminalSessionService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('youpanel.terminal.enabled', true);
    config()->set('youpanel.terminal.gateway_secret', '0123456789abcdef0123456789abcdef');
});

it('keeps terminal disabled by default in repository configuration', function (): void {
    $config = require config_path('youpanel.php');

    expect($config['terminal']['enabled'])->toBeFalse();
});

function terminalFixture(?User $owner = null): array
{
    $owner ??= User::factory()->owner()->create(['password' => Hash::make('CorrectPassword!123')]);
    $website = Website::factory()->create();
    $root = storage_path('app/terminal-fixtures/'.Str::uuid());
    File::ensureDirectoryExists($root);
    File::put($root.'/README.md', 'terminal');
    AllowedPath::factory()->for($website)->create([
        'absolute_path' => $root,
        'is_primary' => true,
        'is_active' => true,
        'can_read' => true,
    ]);
    $website->update(['root_path' => $root]);

    return [$owner, $website, $root];
}

it('creates owner terminal sessions with a short lived one time ticket and no ticket leak at rest', function (): void {
    [$owner] = terminalFixture();

    $response = $this->actingAs($owner)
        ->postJson('/api/v1/terminal/sessions', ['current_password' => 'CorrectPassword!123'])
        ->assertCreated()
        ->assertJsonPath('data.session.scope', 'server')
        ->assertJsonMissingPath('data.token')
        ->assertJsonStructure(['data' => ['ticket', 'websocket_url', 'session' => ['uuid', 'expires_at', 'working_directory']]]);

    $ticket = $response->json('data.ticket');
    $session = TerminalSession::query()->firstOrFail();

    expect($ticket)->toBeString()
        ->and($session->token_hash)->toBe(hash('sha256', $ticket))
        ->and($session->consumed_at)->toBeNull()
        ->and(json_encode($session->toArray()))->not->toContain($ticket)
        ->and(AuditLog::query()->where('action', 'terminal.session.created')->exists())->toBeTrue();
});

it('requires owner role and recent password confirmation for terminal sessions', function (): void {
    $viewer = User::factory()->viewer()->create(['password' => Hash::make('CorrectPassword!123')]);

    $this->actingAs($viewer)
        ->postJson('/api/v1/terminal/sessions', ['current_password' => 'CorrectPassword!123'])
        ->assertStatus(422);

    $owner = User::factory()->owner()->create(['password' => Hash::make('CorrectPassword!123')]);
    $this->actingAs($owner)
        ->postJson('/api/v1/terminal/sessions', ['current_password' => 'wrong'])
        ->assertJsonValidationErrors('current_password');
});

it('consumes terminal tickets once and rejects replay', function (): void {
    [$owner] = terminalFixture();
    $payload = app(TerminalSessionService::class)->create($owner, null, 'CorrectPassword!123');

    expect(app(TerminalSessionService::class)->consumeGatewayTicket($payload['session']->uuid, $payload['ticket'])->id)->toBe($payload['session']->id)
        ->and($payload['session']->refresh()->consumed_at)->not->toBeNull();

    app(TerminalSessionService::class)->consumeGatewayTicket($payload['session']->uuid, $payload['ticket']);
})->throws(OperationBlockedException::class);

it('audits replayed terminal tickets', function (): void {
    [$owner] = terminalFixture();
    $payload = app(TerminalSessionService::class)->create($owner, null, 'CorrectPassword!123');

    app(TerminalSessionService::class)->consumeGatewayTicket($payload['session']->uuid, $payload['ticket']);

    try {
        app(TerminalSessionService::class)->consumeGatewayTicket($payload['session']->uuid, $payload['ticket']);
    } catch (OperationBlockedException) {
        // Expected replay rejection.
    }

    expect(AuditLog::query()->where('action', 'terminal.gateway.replay')->exists())->toBeTrue();
});

it('rejects gateway tickets for sessions not owned by an owner', function (): void {
    $viewer = User::factory()->viewer()->create();
    $ticket = Str::random(80);
    $session = TerminalSession::query()->create([
        'user_id' => $viewer->id,
        'scope' => 'server',
        'token_hash' => hash('sha256', $ticket),
        'working_directory' => base_path(),
        'shell' => '/bin/bash',
        'status' => 'pending',
        'expires_at' => now()->addMinute(),
    ]);

    app(TerminalSessionService::class)->consumeGatewayTicket($session->uuid, $ticket);
})->throws(OperationBlockedException::class);


it('rejects expired terminal tickets', function (): void {
    [$owner] = terminalFixture();
    $payload = app(TerminalSessionService::class)->create($owner, null, 'CorrectPassword!123');

    $payload['session']->update(['expires_at' => now()->subSecond()]);

    app(TerminalSessionService::class)->consumeGatewayTicket($payload['session']->uuid, $payload['ticket']);
})->throws(OperationBlockedException::class);

it('requires gateway secret before websocket ticket validation', function (): void {
    [$owner] = terminalFixture();
    $payload = app(TerminalSessionService::class)->create($owner, null, 'CorrectPassword!123');

    $this->postJson('/api/internal/terminal/sessions/validate', [
        'session' => $payload['session']->uuid,
        'ticket' => $payload['ticket'],
    ])->assertForbidden();

    $this->withHeader('X-YouPanel-Terminal-Gateway-Secret', '0123456789abcdef0123456789abcdef')
        ->postJson('/api/internal/terminal/sessions/validate', [
            'session' => $payload['session']->uuid,
            'ticket' => $payload['ticket'],
        ])
        ->assertOk()
        ->assertJsonPath('data.session.status', 'running');
});

it('records terminal gateway lifecycle events without requiring ticket secrets', function (): void {
    [$owner] = terminalFixture();
    $payload = app(TerminalSessionService::class)->create($owner, null, 'CorrectPassword!123');

    $this->withHeader('X-YouPanel-Terminal-Gateway-Secret', '0123456789abcdef0123456789abcdef')
        ->postJson("/api/internal/terminal/sessions/{$payload['session']->uuid}/events", [
            'event' => 'terminal.session.disconnected',
            'reason' => 'client_closed',
        ])
        ->assertOk();

    expect(AuditLog::query()->where('action', 'terminal.session.disconnected')->exists())->toBeTrue()
        ->and($payload['session']->refresh()->ended_at)->not->toBeNull();
});

it('hardens the terminal gateway source against url credentials and inherited environment', function (): void {
    $source = File::get(base_path('terminal-gateway/server.mjs'));

    expect($source)->toContain('Terminal credentials are not accepted in the WebSocket URL')
        ->and($source)->toContain('type === "authenticate"')
        ->and($source)->toContain('env: terminalEnvironment(session.shell)')
        ->and($source)->toContain('YOUPANEL_TERMINAL_AUTH_TIMEOUT_MS')
        ->and($source)->not->toContain('...process.env')
        ->and($source)->not->toContain('searchParams.get("token")')
        ->and($source)->not->toContain('searchParams.get("ticket")');
});

it('starts website terminal sessions inside approved roots only', function (): void {
    [$owner, $website, $root] = terminalFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/websites/{$website->id}/terminal/sessions", ['current_password' => 'CorrectPassword!123'])
        ->assertCreated()
        ->assertJsonPath('data.session.scope', 'website')
        ->assertJsonPath('data.session.working_directory', realpath($root));

    $unsafe = Website::factory()->create(['root_path' => dirname($root)]);

    $this->actingAs($owner)
        ->postJson("/api/v1/websites/{$unsafe->id}/terminal/sessions", ['current_password' => 'CorrectPassword!123'])
        ->assertStatus(422);
});

it('enforces concurrent terminal session limits', function (): void {
    config()->set('youpanel.terminal.max_concurrent_per_user', 1);
    [$owner] = terminalFixture();

    app(TerminalSessionService::class)->create($owner, null, 'CorrectPassword!123');

    $this->actingAs($owner)
        ->postJson('/api/v1/terminal/sessions', ['current_password' => 'CorrectPassword!123'])
        ->assertStatus(422);
});
