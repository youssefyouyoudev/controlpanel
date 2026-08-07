<?php

use App\Enums\UserRole;
use App\Enums\WebsiteMemberRole;
use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Services\Metrics\MockServerMetricsProvider;
use App\Services\Metrics\ServerMetricsProvider;
use App\Services\ServiceStatusService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    RateLimiter::clear('owner@example.com|127.0.0.1');
});

it('returns health in a consistent envelope', function (): void {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['data' => ['application', 'status', 'database', 'timestamp'], 'meta' => ['request_id']]);
});

it('returns public readiness and security headers', function (): void {
    $this->getJson('/api/ready')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertJsonPath('data.ready', true);
});

it('logs in with normalized credentials, records audit data, and exposes the current user', function (): void {
    $user = User::factory()->owner()->create(['email' => 'owner@example.com', 'password' => Hash::make('CorrectPassword!123')]);

    $this->postJson('/api/v1/auth/login', ['email' => ' OWNER@example.com ', 'password' => 'CorrectPassword!123', 'remember' => true])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'owner@example.com');

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'auth.login')->exists())->toBeTrue();

    $this->getJson('/api/v1/auth/user')
        ->assertOk()
        ->assertJsonPath('data.user.role', UserRole::Owner->value);
});

it('returns validation errors for missing login fields', function (): void {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonValidationErrors(['email', 'password']);
});

it('returns cors headers on unauthenticated current-user responses', function (): void {
    config()->set('cors.allowed_origins', ['https://control.youssefyouyou.com']);

    $this->withHeaders([
        'Origin' => 'https://control.youssefyouyou.com',
        'Accept' => 'application/json',
    ])
        ->getJson('/api/v1/auth/user')
        ->assertUnauthorized()
        ->assertHeader('Access-Control-Allow-Origin', 'https://control.youssefyouyou.com')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});

it('requires two-factor challenge and accepts authenticator codes', function (): void {
    $user = User::factory()->owner()->create(['email' => 'owner@example.com', 'password' => Hash::make('CorrectPassword!123')]);
    app(TwoFactorAuthenticationService::class)->startEnrollment($user);
    $user = $user->fresh();
    $code = app(Google2FA::class)->getCurrentOtp($user->two_factor_secret);
    app(TwoFactorAuthenticationService::class)->confirm($user, $code);

    $this->withHeader('Referer', config('youpanel.frontend_url'))
        ->withSession([])
        ->postJson('/api/v1/auth/login', ['email' => 'owner@example.com', 'password' => 'CorrectPassword!123'])
        ->assertAccepted()
        ->assertJsonPath('data.requires_two_factor', true);

    $this->getJson('/api/v1/auth/user')->assertUnauthorized();

    $this->withHeader('Referer', config('youpanel.frontend_url'))
        ->postJson('/api/v1/auth/two-factor-challenge', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'owner@example.com');

    expect(AuditLog::query()->where('action', 'auth.two_factor_required')->exists())->toBeTrue();
});

it('protects recovery codes and consumes them only once', function (): void {
    $user = User::factory()->owner()->create(['email' => 'owner@example.com', 'password' => Hash::make('CorrectPassword!123')]);
    $setup = app(TwoFactorAuthenticationService::class)->startEnrollment($user);
    $user = $user->fresh();
    app(TwoFactorAuthenticationService::class)->confirm($user, app(Google2FA::class)->getCurrentOtp($user->two_factor_secret));

    expect(json_encode($user->fresh()->two_factor_recovery_codes, JSON_THROW_ON_ERROR))->not->toContain($setup['recovery_codes'][0]);

    $this->withHeader('Referer', config('youpanel.frontend_url'))->withSession([])->postJson('/api/v1/auth/login', ['email' => 'owner@example.com', 'password' => 'CorrectPassword!123'])->assertAccepted();
    $this->withHeader('Referer', config('youpanel.frontend_url'))->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $setup['recovery_codes'][0]])->assertOk();
    $this->postJson('/api/v1/auth/logout')->assertOk();

    $this->withHeader('Referer', config('youpanel.frontend_url'))->withSession([])->postJson('/api/v1/auth/login', ['email' => 'owner@example.com', 'password' => 'CorrectPassword!123'])->assertAccepted();
    $this->withHeader('Referer', config('youpanel.frontend_url'))->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $setup['recovery_codes'][0]])->assertStatus(422);
});

it('rejects failed logins and inactive users', function (): void {
    User::factory()->owner()->create(['email' => 'owner@example.com', 'password' => Hash::make('CorrectPassword!123')]);
    User::factory()->inactive()->create(['email' => 'inactive@example.com', 'password' => Hash::make('CorrectPassword!123')]);

    $this->postJson('/api/v1/auth/login', ['email' => 'owner@example.com', 'password' => 'wrong'])
        ->assertStatus(422);

    $this->postJson('/api/v1/auth/login', ['email' => 'inactive@example.com', 'password' => 'CorrectPassword!123'])
        ->assertForbidden();
});

it('rate limits repeated login attempts', function (): void {
    User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('CorrectPassword!123')]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'owner@example.com', 'password' => 'wrong']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'owner@example.com', 'password' => 'wrong'])
        ->assertStatus(429);
});

it('blocks unsafe mutations in portfolio demo mode', function (): void {
    config()->set('youpanel.portfolio_demo', true);
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->putJson('/api/v1/auth/profile', ['name' => 'Demo', 'email' => 'demo@example.com', 'timezone' => 'UTC'])
        ->assertStatus(423)
        ->assertJsonPath('message', 'Portfolio demo mode is read-only. Mutating actions are disabled.');
});

it('logs out and invalidates the session', function (): void {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect(AuditLog::query()->where('action', 'auth.logout')->exists())->toBeTrue();
});

it('enforces owner, developer, and viewer website permissions', function (): void {
    $owner = User::factory()->owner()->create();
    $developer = User::factory()->developer()->create();
    $viewer = User::factory()->viewer()->create();
    $server = Server::factory()->create();
    $website = Website::factory()->for($server)->create();

    $website->members()->attach($developer, ['role' => WebsiteMemberRole::Developer->value]);
    $website->members()->attach($viewer, ['role' => WebsiteMemberRole::Viewer->value]);

    expect($owner->can('update', $website))->toBeTrue()
        ->and($developer->can('update', $website))->toBeTrue()
        ->and($viewer->can('update', $website))->toBeFalse();
});

it('isolates website membership in dashboard responses', function (): void {
    $developer = User::factory()->developer()->create();
    $server = Server::factory()->create();
    $visible = Website::factory()->for($server)->create(['name' => 'Visible']);
    Website::factory()->for($server)->create(['name' => 'Hidden']);
    $visible->members()->attach($developer, ['role' => WebsiteMemberRole::Developer->value]);

    $this->actingAs($developer)
        ->getJson('/api/v1/dashboard/websites')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Visible'])
        ->assertJsonMissing(['name' => 'Hidden']);
});

it('does not leak sensitive website fields to non owners', function (): void {
    $viewer = User::factory()->viewer()->create();
    $website = Website::factory()->create(['root_path' => '/var/www/secret', 'metadata' => ['secret' => 'nope']]);
    $website->members()->attach($viewer, ['role' => WebsiteMemberRole::Viewer->value]);

    $this->actingAs($viewer)
        ->getJson("/api/v1/websites/{$website->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.website.display_path')
        ->assertJsonMissing(['root_path' => '/var/www/secret'])
        ->assertJsonMissing(['secret' => 'nope']);
});

it('uses the metrics fallback contract and service allowlist', function (): void {
    app()->instance(ServerMetricsProvider::class, new MockServerMetricsProvider);

    $user = User::factory()->viewer()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->assertJsonPath('data.metrics.available', true);

    expect(app(ServiceStatusService::class)->status('nginx')['status'])->toBe('running');
    app(ServiceStatusService::class)->status('ssh');
})->throws(HttpException::class);

it('filters dashboard data and audit logs by authorization', function (): void {
    $owner = User::factory()->owner()->create();
    $viewer = User::factory()->viewer()->create();
    $visible = Website::factory()->create(['name' => 'Allowed Site']);
    $hidden = Website::factory()->create(['name' => 'Private Site']);
    $visible->members()->attach($viewer, ['role' => WebsiteMemberRole::Viewer->value]);
    AuditLog::factory()->for($owner)->for($visible)->create(['action' => 'visible.event']);
    AuditLog::factory()->for($owner)->for($hidden)->create(['action' => 'hidden.event']);

    $this->actingAs($viewer)
        ->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonFragment(['total' => 1])
        ->assertJsonFragment(['action' => 'visible.event'])
        ->assertJsonMissing(['action' => 'hidden.event']);
});
