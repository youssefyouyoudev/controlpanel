<?php

use App\Enums\WebsiteMemberRole;
use App\Models\AllowedPath;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Website;
use App\Services\Discovery\NginxWebsiteDiscoveryService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function discoveryFixture(): array
{
    $base = storage_path('app/discovery-fixtures/'.Str::uuid());
    $nginx = $base.'/nginx';
    $laravel = $base.'/apps/laravel-site';
    $next = $base.'/apps/next-site';

    File::ensureDirectoryExists($nginx);
    File::ensureDirectoryExists($laravel.'/public');
    File::ensureDirectoryExists($next);
    File::put($laravel.'/artisan', '#!/usr/bin/env php');
    File::put($laravel.'/composer.json', '{"name":"demo/laravel"}');
    File::put($laravel.'/public/index.php', '<?php echo "ok";');
    File::put($next.'/package.json', '{"dependencies":{"next":"16.0.0","react":"19.0.0"}}');
    File::put($next.'/next.config.mjs', 'export default {};');
    File::put($nginx.'/sites.conf', <<<NGINX
server {
    listen 80;
    server_name example.test www.example.test api.example.test;
    root {$laravel}/public;
    location / {
        try_files \$uri /index.php?\$query_string;
    }
}

server {
    listen 443 ssl;
    server_name app.example.test;
    root {$next};
    ssl_certificate {$base}/cert.pem;
}

server {
    listen 80;
    server_name proxy.example.test;
    location / {
        proxy_pass http://127.0.0.1:3000;
    }
}
NGINX);

    config()->set('youpanel.discovery.nginx_paths', [$nginx]);
    config()->set('youpanel.discovery.health_checks', false);

    return [$base, $nginx, $laravel, $next];
}

it('discovers nginx root and reverse proxy websites with aliases', function (): void {
    [$base, $nginx, $laravel, $next] = discoveryFixture();

    $sites = app(NginxWebsiteDiscoveryService::class)->scan();

    expect($sites)->toHaveCount(3);

    $laravelSite = collect($sites)->firstWhere('primary_domain', 'example.test');
    expect($laravelSite['domain_aliases'])->toContain('www.example.test', 'api.example.test')
        ->and($laravelSite['document_root'])->toBe($laravel.'/public')
        ->and($laravelSite['root_path'])->toBe($laravel)
        ->and($laravelSite['application_type'])->toBe('laravel');

    $nextSite = collect($sites)->firstWhere('primary_domain', 'app.example.test');
    expect($nextSite['application_type'])->toBe('nextjs')
        ->and($nextSite['https_enabled'])->toBeTrue();

    $proxySite = collect($sites)->firstWhere('primary_domain', 'proxy.example.test');
    expect($proxySite['application_type'])->toBe('reverse_proxy')
        ->and($proxySite['proxy_destination'])->toBe('http://127.0.0.1:3000');
});

it('synchronizes discovered websites without duplicates and makes them owner visible', function (): void {
    discoveryFixture();
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->postJson('/api/v1/websites/discovery/sync')
        ->assertOk()
        ->assertJsonPath('data.created', 3);

    $this->actingAs($owner)
        ->postJson('/api/v1/websites/discovery/sync')
        ->assertOk()
        ->assertJsonPath('data.created', 0);

    expect(Website::query()->count())->toBe(3)
        ->and(Website::query()->where('domain', 'example.test')->first()?->metadata['discovery']['domain_aliases'])->toContain('www.example.test')
        ->and(AllowedPath::query()->whereHas('website', fn ($query) => $query->where('domain', 'example.test'))->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'websites.synchronized')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->getJson('/api/v1/websites')
        ->assertOk()
        ->assertJsonFragment(['domain' => 'example.test']);
});

it('keeps unauthorized users from scanning and only shows assigned websites', function (): void {
    discoveryFixture();
    $owner = User::factory()->owner()->create();
    $viewer = User::factory()->viewer()->create();

    $this->actingAs($viewer)
        ->postJson('/api/v1/websites/discovery/scan')
        ->assertForbidden();

    $this->actingAs($owner)->postJson('/api/v1/websites/discovery/sync')->assertOk();

    $this->actingAs($viewer)
        ->getJson('/api/v1/websites')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $website = Website::query()->where('domain', 'example.test')->firstOrFail();
    $website->members()->attach($viewer, ['role' => WebsiteMemberRole::Viewer->value]);

    $this->actingAs($viewer)
        ->getJson('/api/v1/websites')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissingPath('data.0.display_path');
});

it('redacts git credentials during discovery', function (): void {
    [$base, $nginx, $laravel] = discoveryFixture();
    exec('git init '.escapeshellarg($laravel));
    exec('git -C '.escapeshellarg($laravel).' config user.email test@example.test');
    exec('git -C '.escapeshellarg($laravel).' config user.name Test');
    exec('git -C '.escapeshellarg($laravel).' remote add origin https://token:secret@example.com/private/repo.git');
    exec('git -C '.escapeshellarg($laravel).' add .');
    exec('git -C '.escapeshellarg($laravel).' commit -m initial');

    $site = collect(app(NginxWebsiteDiscoveryService::class)->scan())->firstWhere('primary_domain', 'example.test');

    expect($site['git']['remote_url'])->toBe('https://[redacted]@example.com/private/repo.git')
        ->and(json_encode($site))->not->toContain('token:secret');
});
