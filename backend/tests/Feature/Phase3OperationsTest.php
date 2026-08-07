<?php

use App\Enums\ActionExecutionStatus;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\WebsiteMemberRole;
use App\Models\ActionExecution;
use App\Models\AllowedPath;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Models\WebsiteLogSource;
use App\Services\Operations\GitService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function operationFixture(?User $user = null, array $component = []): array
{
    $user ??= User::factory()->developer()->create();
    $website = Website::factory()->create();
    $website->members()->syncWithoutDetaching([$user->id => ['role' => WebsiteMemberRole::Developer->value]]);
    $rootPath = storage_path('app/operation-fixtures/'.Str::uuid());
    File::ensureDirectoryExists($rootPath.'/backend');
    File::put($rootPath.'/backend/artisan', '#!/usr/bin/env php');
    File::put($rootPath.'/backend/composer.json', '{"name":"demo/app"}');
    File::put($rootPath.'/backend/package.json', '{"scripts":{"build":"echo ok","lint":"echo ok","test":"echo ok"}}');
    File::put($rootPath.'/backend/package-lock.json', '{"lockfileVersion":3}');

    $root = AllowedPath::factory()->for($website)->create([
        'absolute_path' => $rootPath,
        'is_primary' => true,
        'is_active' => true,
    ]);

    $componentModel = WebsiteComponent::factory()->for($website)->create([
        'relative_working_directory' => 'backend',
        'type' => 'laravel',
        ...$component,
    ]);

    return [$user, $website, $root, $componentModel, $rootPath];
}

it('executes only catalog actions and rejects raw commands', function (): void {
    [$developer, $website, $root, $component] = operationFixture();

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/actions/laravel.clear_cache/execute", [
            'website_component_id' => $component->id,
            'command' => 'php artisan migrate',
        ])
        ->assertJsonValidationErrors('command');

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/actions/not.real/execute", ['website_component_id' => $component->id])
        ->assertStatus(422);

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/actions/laravel.clear_cache/execute", ['website_component_id' => $component->id])
        ->assertAccepted()
        ->assertJsonPath('data.execution.action_key', 'laravel.clear_cache');

    expect(ActionExecution::query()->where('action_key', 'laravel.clear_cache')->first()?->status)->toBe(ActionExecutionStatus::Succeeded)
        ->and(AuditLog::query()->where('action', 'action.completed')->exists())->toBeTrue();
});

it('enforces website membership and role restrictions', function (): void {
    [$developer, $website, $root, $component] = operationFixture();
    $outsider = User::factory()->developer()->create();
    $viewer = User::factory()->viewer()->create();
    $website->members()->attach($viewer, ['role' => WebsiteMemberRole::Viewer->value]);

    $this->actingAs($outsider)
        ->postJson("/api/v1/websites/{$website->id}/actions/laravel.clear_cache/execute", ['website_component_id' => $component->id])
        ->assertStatus(422);

    $this->actingAs($viewer)
        ->postJson("/api/v1/websites/{$website->id}/actions/laravel.clear_cache/execute", ['website_component_id' => $component->id])
        ->assertStatus(422);
});

it('requires meaningful high risk confirmation', function (): void {
    [$developer, $website, $root, $component] = operationFixture(User::factory()->developer()->create(['password' => Hash::make('CorrectPassword!123')]));

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/actions/laravel.migrate/execute", ['website_component_id' => $component->id])
        ->assertStatus(422);

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/actions/laravel.migrate/execute", [
            'website_component_id' => $component->id,
            'options' => ['confirmed' => true, 'typed_website_name' => $website->name, 'password' => 'CorrectPassword!123'],
        ])
        ->assertAccepted();
});

it('blocks component working directory escape', function (): void {
    $owner = User::factory()->owner()->create();
    [$ignored, $website] = operationFixture($owner);

    $this->actingAs($owner)
        ->postJson("/api/v1/websites/{$website->id}/components", [
            'name' => 'Bad',
            'slug' => 'bad',
            'type' => 'laravel',
            'relative_working_directory' => '../outside',
        ])
        ->assertJsonValidationErrors('relative_working_directory');
});

it('blocks dirty git pulls before queueing', function (): void {
    [$developer, $website, $root, $component] = operationFixture();

    app()->bind(GitService::class, fn () => new class extends GitService
    {
        public function __construct() {}

        public function status(string $workingDirectory): array
        {
            return ['branch' => 'main', 'latest_commit' => 'abc mock', 'remote_url' => null, 'dirty' => true, 'changes' => ['M README.md'], 'ahead' => null, 'behind' => null];
        }
    });

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/git/pull")
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Git pull is blocked because local files have changes.']);
});

it('does not accept arbitrary pm2 process names', function (): void {
    [$developer, $website, $root, $component] = operationFixture(component: ['type' => 'node', 'process_manager' => 'pm2', 'process_name' => 'approved-process']);

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/actions/pm2.restart_assigned_process/execute", [
            'website_component_id' => $component->id,
            'process_name' => 'other-process',
        ])
        ->assertJsonValidationErrors('process_name');
});

it('reads only configured log sources with redaction', function (): void {
    [$developer, $website] = operationFixture();
    $source = WebsiteLogSource::factory()->for($website)->create(['slug' => 'app']);

    $this->actingAs($developer)
        ->getJson("/api/v1/websites/{$website->id}/logs/{$source->id}?lines=100&search=token")
        ->assertOk()
        ->assertJsonPath('data.redacted', true)
        ->assertJsonMissing(['token=secret']);
});

it('rejects forbidden health check targets', function (): void {
    $owner = User::factory()->owner()->create();
    [$ignored, $website] = operationFixture($owner);

    $this->actingAs($owner)
        ->postJson("/api/v1/websites/{$website->id}/health", ['url' => 'http://127.0.0.1:8000', 'expected_status' => 200])
        ->assertStatus(422);
});

it('creates backups in controlled private storage and stages restore with confirmation', function (): void {
    $owner = User::factory()->owner()->create(['password' => Hash::make('CorrectPassword!123')]);
    [$ignored, $website] = operationFixture($owner);

    $this->actingAs($owner)
        ->postJson("/api/v1/websites/{$website->id}/backups", ['type' => BackupType::Files->value])
        ->assertAccepted();

    $backup = Backup::query()->firstOrFail();
    expect($backup->status)->toBe(BackupStatus::Succeeded)
        ->and(str_starts_with((string) $backup->storage_path, 'backups/'))->toBeTrue()
        ->and(file_exists(storage_path('app/private/'.$backup->storage_path)))->toBeTrue();

    $this->actingAs($owner)
        ->postJson("/api/v1/websites/{$website->id}/backups/{$backup->uuid}/restore", [
            'typed_website_name' => $website->name,
            'password' => 'CorrectPassword!123',
        ])
        ->assertOk();
});

it('creates operation notifications', function (): void {
    [$developer, $website, $root, $component] = operationFixture();

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/actions/laravel.clear_cache/execute", ['website_component_id' => $component->id])
        ->assertAccepted();

    $this->actingAs($developer)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);
});
