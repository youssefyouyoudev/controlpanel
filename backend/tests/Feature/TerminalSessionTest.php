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

it('creates owner terminal sessions with a short lived token and no token leak at rest', function (): void {
    [$owner] = terminalFixture();

    $response = $this->actingAs($owner)
        ->postJson('/api/v1/terminal/sessions', ['current_password' => 'CorrectPassword!123'])
        ->assertCreated()
        ->assertJsonPath('data.session.scope', 'server')
        ->assertJsonStructure(['data' => ['token', 'websocket_url', 'session' => ['uuid', 'expires_at', 'working_directory']]]);

    $token = $response->json('data.token');
    $session = TerminalSession::query()->firstOrFail();

    expect($token)->toBeString()
        ->and($session->token_hash)->toBe(hash('sha256', $token))
        ->and(json_encode($session->toArray()))->not->toContain($token)
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

it('validates terminal token expiry', function (): void {
    [$owner] = terminalFixture();
    $payload = app(TerminalSessionService::class)->create($owner, null, 'CorrectPassword!123');

    expect(app(TerminalSessionService::class)->validateToken($payload['session']->uuid, $payload['token'])->id)->toBe($payload['session']->id);

    $payload['session']->update(['expires_at' => now()->subSecond()]);

    app(TerminalSessionService::class)->validateToken($payload['session']->uuid, $payload['token']);
})->throws(OperationBlockedException::class);

it('requires gateway secret before websocket token validation', function (): void {
    config()->set('youpanel.terminal.gateway_secret', 'gateway-secret');
    [$owner] = terminalFixture();
    $payload = app(TerminalSessionService::class)->create($owner, null, 'CorrectPassword!123');

    $this->postJson('/api/internal/terminal/sessions/validate', [
        'session' => $payload['session']->uuid,
        'token' => $payload['token'],
    ])->assertForbidden();

    $this->withHeader('X-YouPanel-Terminal-Gateway-Secret', 'gateway-secret')
        ->postJson('/api/internal/terminal/sessions/validate', [
            'session' => $payload['session']->uuid,
            'token' => $payload['token'],
        ])
        ->assertOk()
        ->assertJsonPath('data.session.status', 'running');
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
