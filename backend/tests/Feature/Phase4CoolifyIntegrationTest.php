<?php

use App\Enums\DeploymentApprovalStatus;
use App\Enums\WebsiteMemberRole;
use App\Models\AllowedPath;
use App\Models\AuditLog;
use App\Models\CoolifyResourceLink;
use App\Models\CoolifySyncRun;
use App\Models\Deployment;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Services\Coolify\CoolifySynchronizationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function phase4Fixture(?User $user = null): array
{
    $user ??= User::factory()->developer()->create();
    $website = Website::factory()->create(['name' => 'Youssef Portfolio', 'repository_branch' => 'main']);
    $website->members()->syncWithoutDetaching([$user->id => ['role' => WebsiteMemberRole::Developer->value]]);
    $rootPath = storage_path('app/phase4-fixtures/'.Str::uuid());
    File::ensureDirectoryExists($rootPath.'/backend');
    File::put($rootPath.'/backend/artisan', '#!/usr/bin/env php');
    File::put($rootPath.'/backend/composer.json', '{"name":"demo/app"}');

    AllowedPath::factory()->for($website)->create([
        'absolute_path' => $rootPath,
        'is_primary' => true,
        'is_active' => true,
    ]);

    $component = WebsiteComponent::factory()->for($website)->create([
        'name' => 'Backend',
        'type' => 'laravel',
        'relative_working_directory' => 'backend',
    ]);

    return [$user, $website, $component];
}

beforeEach(function (): void {
    config()->set('coolify.driver', 'mock');
    config()->set('coolify.enabled', false);
    config()->set('coolify.api_token', '67|super-secret-token');
});

it('keeps Coolify token backend-only and owner-only', function (): void {
    $owner = User::factory()->owner()->create();
    $developer = User::factory()->developer()->create();

    $this->actingAs($developer)->getJson('/api/v1/integrations/coolify/status')->assertForbidden();

    $this->actingAs($owner)
        ->getJson('/api/v1/integrations/coolify/status')
        ->assertOk()
        ->assertJsonPath('data.token_configured', true)
        ->assertJsonMissing(['67|super-secret-token']);
});

it('discovers mock resources and links only after verification', function (): void {
    $owner = User::factory()->owner()->create();
    [$ignored, $website, $component] = phase4Fixture($owner);

    $this->actingAs($owner)
        ->getJson('/api/v1/integrations/coolify/resources?type=application')
        ->assertOk()
        ->assertJsonPath('data.0.coolify_uuid', 'mock-app-portfolio');

    $this->actingAs($owner)
        ->postJson("/api/v1/websites/{$website->id}/coolify-links", [
            'website_component_id' => $component->id,
            'resource_type' => 'application',
            'coolify_uuid' => 'mock-app-portfolio',
            'is_primary' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.link.coolify_uuid', 'mock-app-portfolio');

    expect(CoolifyResourceLink::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'coolify.link.created')->exists())->toBeTrue();
});

it('removing a link never deletes the Coolify resource', function (): void {
    $owner = User::factory()->owner()->create();
    [$ignored, $website] = phase4Fixture($owner);
    $link = CoolifyResourceLink::factory()->for($website)->create(['coolify_uuid' => 'mock-app-portfolio']);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/websites/{$website->id}/coolify-links/{$link->id}")
        ->assertOk()
        ->assertJsonFragment(['message' => 'Coolify link removed. The Coolify resource was not deleted.']);

    expect(CoolifyResourceLink::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'coolify.link.removed')->whereJsonContains('metadata->coolify_resource_deleted', false)->exists())->toBeTrue();
});

it('filters linked resources and deployments by website membership', function (): void {
    [$developer, $website] = phase4Fixture();
    $otherWebsite = Website::factory()->create();
    $visibleLink = CoolifyResourceLink::factory()->for($website)->create(['coolify_uuid' => 'visible']);
    CoolifyResourceLink::factory()->for($otherWebsite)->create(['coolify_uuid' => 'hidden']);
    Deployment::factory()->for($website)->create(['coolify_resource_link_id' => $visibleLink->id]);
    Deployment::factory()->for($otherWebsite)->create();

    $this->actingAs($developer)
        ->getJson("/api/v1/websites/{$website->id}/resources")
        ->assertOk()
        ->assertJsonFragment(['coolify_uuid' => 'visible'])
        ->assertJsonMissing(['coolify_uuid' => 'hidden']);

    $this->actingAs($developer)
        ->getJson('/api/v1/deployments')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rejects browser-controlled Coolify UUIDs for resource controls', function (): void {
    [$developer, $website] = phase4Fixture();
    $link = CoolifyResourceLink::factory()->for($website)->create(['resource_type' => 'application', 'coolify_uuid' => 'mock-app-portfolio']);

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/resources/{$link->id}/restart", [
            'coolify_uuid' => 'attacker-controlled',
            'confirmed' => true,
        ])
        ->assertJsonValidationErrors('coolify_uuid');
});

it('requires production deployment approval for developers and validates unchanged approval', function (): void {
    [$developer, $website] = phase4Fixture();
    $owner = User::factory()->owner()->create();
    $link = CoolifyResourceLink::factory()->for($website)->create(['resource_type' => 'application', 'coolify_uuid' => 'mock-app-portfolio']);

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/deployments", [
            'resource_link_id' => $link->id,
            'branch' => 'main',
            'confirmed' => true,
            'typed_website_name' => $website->name,
        ])
        ->assertAccepted()
        ->assertJsonPath('data.deployment.status', 'awaiting_approval');

    $deployment = Deployment::query()->firstOrFail();
    expect($deployment->approval?->status)->toBe(DeploymentApprovalStatus::Pending);

    $this->actingAs($owner)
        ->postJson("/api/v1/deployments/{$deployment->uuid}/approve")
        ->assertOk()
        ->assertJsonPath('data.deployment.status', 'succeeded');
});

it('invalidates deployment approval when protected details change', function (): void {
    [$developer, $website] = phase4Fixture();
    $owner = User::factory()->owner()->create();
    $link = CoolifyResourceLink::factory()->for($website)->create(['resource_type' => 'application']);

    $this->actingAs($developer)->postJson("/api/v1/websites/{$website->id}/deployments", [
        'resource_link_id' => $link->id,
        'branch' => 'main',
        'confirmed' => true,
        'typed_website_name' => $website->name,
    ])->assertAccepted();

    $deployment = Deployment::query()->firstOrFail();
    $deployment->update(['branch' => 'release']);

    $this->actingAs($owner)
        ->postJson("/api/v1/deployments/{$deployment->uuid}/approve")
        ->assertStatus(422);

    expect($deployment->approval->refresh()->status)->toBe(DeploymentApprovalStatus::Invalidated);
});

it('limits and redacts deployment logs', function (): void {
    $owner = User::factory()->owner()->create();
    [$ignored, $website] = phase4Fixture($owner);
    $link = CoolifyResourceLink::factory()->for($website)->create(['resource_type' => 'application']);
    $deployment = Deployment::factory()->for($website)->create([
        'coolify_resource_link_id' => $link->id,
        'coolify_deployment_uuid' => 'mock-deploy-success',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/deployments/{$deployment->uuid}/logs")
        ->assertOk()
        ->assertJsonPath('data.redacted', true)
        ->assertJsonMissing(['TOKEN=secret-value']);
});

it('uses locked synchronization without deleting resources', function (): void {
    $owner = User::factory()->owner()->create();
    $lock = Cache::lock('coolify:sync:all', 300);
    $lock->get();

    $run = app(CoolifySynchronizationService::class)->synchronize($owner);
    $lock->release();

    expect($run->status->value)->toBe('locked')
        ->and(CoolifySyncRun::query()->count())->toBe(1);
});

it('restricted console accepts only aliases and no host terminal exists', function (): void {
    [$developer, $website, $component] = phase4Fixture();

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/console/execute", [
            'website_component_id' => $component->id,
            'command_alias' => 'git.status',
            'command' => 'git status && cat .env',
        ])
        ->assertJsonValidationErrors('command');

    $response = $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/console/execute", [
            'website_component_id' => $component->id,
            'command_alias' => 'git.status',
        ])
        ->assertAccepted()
        ->assertJsonMissing(['TOKEN=secret']);

    $uuid = $response->json('data.execution.uuid');
    $this->actingAs($developer)
        ->getJson("/api/v1/console-executions/{$uuid}")
        ->assertOk()
        ->assertJsonPath('data.execution.status', 'succeeded');

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/containers/12/terminal/sessions")
        ->assertNotFound();
});

it('does not access the Docker socket from application code', function (): void {
    $code = collect(File::allFiles(app_path()))
        ->map(fn (SplFileInfo $file): string => File::get($file->getPathname()))
        ->implode("\n");

    expect($code)->not->toContain('docker exec')
        ->and($code)->not->toContain('Process([\'docker\'')
        ->and($code)->not->toContain('Process(["docker"');
});
