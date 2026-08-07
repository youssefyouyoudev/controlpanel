<?php

use App\Enums\WebsiteMemberRole;
use App\Exceptions\InvalidWorkspacePathException;
use App\Models\AllowedPath;
use App\Models\AuditLog;
use App\Models\FileRevision;
use App\Models\TrashEntry;
use App\Models\User;
use App\Models\Website;
use App\Services\ArchiveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function workspaceFixture(?User $user = null, ?Website $website = null, array $root = []): array
{
    $user ??= User::factory()->developer()->create();
    $website ??= Website::factory()->create();
    $path = storage_path('app/test-workspaces/'.Str::uuid());
    File::ensureDirectoryExists($path);
    file_put_contents($path.'/README.md', "# Hello\n");
    file_put_contents($path.'/image.bin', "abc\0def");
    file_put_contents($path.'/.env', "SECRET=1\n");
    File::ensureDirectoryExists($path.'/src');
    file_put_contents($path.'/src/app.ts', "console.log('hi');\n");
    $website->members()->syncWithoutDetaching([$user->id => ['role' => WebsiteMemberRole::Developer->value]]);
    $allowedPath = AllowedPath::factory()->for($website)->create([
        'absolute_path' => $path,
        'is_active' => true,
        ...$root,
    ]);

    return [$user, $website, $allowedPath, $path];
}

it('lets owners configure an approved root and hides absolute paths from non owners', function (): void {
    $owner = User::factory()->owner()->create();
    $viewer = User::factory()->viewer()->create();
    $website = Website::factory()->create();
    $website->members()->attach($viewer, ['role' => WebsiteMemberRole::Viewer->value]);
    $path = storage_path('app/test-workspaces/'.Str::uuid());
    File::ensureDirectoryExists($path);

    $this->actingAs($owner)
        ->postJson("/api/v1/websites/{$website->id}/file-roots", [
            'name' => 'Backend',
            'relative_label' => 'Backend',
            'absolute_path' => $path,
            'is_active' => true,
            'can_read' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.root.absolute_path', realpath($path));

    $this->actingAs($viewer)
        ->getJson("/api/v1/websites/{$website->id}/file-roots")
        ->assertOk()
        ->assertJsonMissing(['absolute_path' => realpath($path)]);
});

it('blocks non owner root configuration', function (): void {
    [$developer, $website] = workspaceFixture();

    $this->actingAs($developer)
        ->postJson("/api/v1/websites/{$website->id}/file-roots", ['name' => 'Bad', 'absolute_path' => storage_path(), 'is_active' => true])
        ->assertForbidden();
});

it('rejects dangerous system roots', function (): void {
    $owner = User::factory()->owner()->create();
    $website = Website::factory()->create();

    $this->actingAs($owner)
        ->postJson("/api/v1/websites/{$website->id}/file-roots", [
            'name' => 'System',
            'absolute_path' => '/',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('absolute_path');
})->skip(PHP_OS_FAMILY === 'Windows', 'POSIX root validation is not meaningful on Windows.');

it('enforces download limits', function (): void {
    config(['youpanel.files.max_download_bytes' => 3]);
    [$user, $website, $root] = workspaceFixture();

    $this->actingAs($user)
        ->get("/api/v1/websites/{$website->id}/files/download?allowed_path_id={$root->id}&path=README.md")
        ->assertStatus(413);
});

it('enforces website membership isolation for roots', function (): void {
    [$developer, $website, $root] = workspaceFixture();
    $other = User::factory()->developer()->create();

    $this->actingAs($other)
        ->getJson("/api/v1/websites/{$website->id}/files?allowed_path_id={$root->id}")
        ->assertForbidden();

    $this->actingAs($developer)
        ->getJson("/api/v1/websites/{$website->id}/files?allowed_path_id={$root->id}")
        ->assertOk()
        ->assertJsonFragment(['name' => 'README.md']);
});

it('prevents viewers and root flags from writing', function (): void {
    $viewer = User::factory()->viewer()->create();
    [$ignored, $website, $root] = workspaceFixture($viewer, root: ['can_write' => true]);
    $website->members()->sync([$viewer->id => ['role' => WebsiteMemberRole::Viewer->value]]);

    $this->actingAs($viewer)
        ->putJson("/api/v1/websites/{$website->id}/files/content", ['allowed_path_id' => $root->id, 'path' => 'README.md', 'content' => 'x', 'checksum' => hash('sha256', "# Hello\n")])
        ->assertForbidden();

    $editor = User::factory()->editor()->create();
    [$ignored, $editorWebsite, $readOnlyRoot] = workspaceFixture($editor, root: ['can_write' => false]);
    $editorWebsite->members()->sync([$editor->id => ['role' => WebsiteMemberRole::Editor->value]]);

    $this->actingAs($editor)
        ->putJson("/api/v1/websites/{$editorWebsite->id}/files/content", ['allowed_path_id' => $readOnlyRoot->id, 'path' => 'README.md', 'content' => 'x', 'checksum' => hash('sha256', "# Hello\n")])
        ->assertForbidden();
});

it('rejects absolute traversal encoded traversal null bytes and missing parents', function (): void {
    [$user, $website, $root] = workspaceFixture();

    foreach (['/etc/passwd', '../README.md', '%2e%2e/README.md', "bad\0path"] as $path) {
        $this->actingAs($user)
            ->getJson("/api/v1/websites/{$website->id}/files/content?allowed_path_id={$root->id}&path=".rawurlencode($path))
            ->assertStatus(422);
    }

    $this->actingAs($user)
        ->postJson("/api/v1/websites/{$website->id}/files/create", ['allowed_path_id' => $root->id, 'path' => 'missing/child.txt'])
        ->assertStatus(422);
});

it('hides and denies protected files for non owners', function (): void {
    [$user, $website, $root] = workspaceFixture();

    $this->actingAs($user)
        ->getJson("/api/v1/websites/{$website->id}/files?allowed_path_id={$root->id}")
        ->assertOk()
        ->assertJsonMissing(['name' => '.env']);

    $this->actingAs($user)
        ->getJson("/api/v1/websites/{$website->id}/files/content?allowed_path_id={$root->id}&path=.env")
        ->assertForbidden();
});

it('does not edit binary or oversized files', function (): void {
    config(['youpanel.files.max_edit_bytes' => 4]);
    [$user, $website, $root] = workspaceFixture();

    $this->actingAs($user)
        ->getJson("/api/v1/websites/{$website->id}/files/content?allowed_path_id={$root->id}&path=image.bin")
        ->assertStatus(422);

    $this->actingAs($user)
        ->getJson("/api/v1/websites/{$website->id}/files/content?allowed_path_id={$root->id}&path=README.md")
        ->assertStatus(422);
});

it('saves with optimistic concurrency and creates revisions', function (): void {
    [$user, $website, $root, $path] = workspaceFixture();
    $checksum = hash_file('sha256', $path.'/README.md');

    $this->actingAs($user)
        ->putJson("/api/v1/websites/{$website->id}/files/content", ['allowed_path_id' => $root->id, 'path' => 'README.md', 'content' => "# Saved\n", 'checksum' => $checksum])
        ->assertOk();

    expect(file_get_contents($path.'/README.md'))->toBe("# Saved\n")
        ->and(FileRevision::query()->where('relative_path', 'README.md')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'file.saved')->exists())->toBeTrue();

    $this->actingAs($user)
        ->putJson("/api/v1/websites/{$website->id}/files/content", ['allowed_path_id' => $root->id, 'path' => 'README.md', 'content' => 'stale', 'checksum' => $checksum])
        ->assertConflict();
});

it('uploads with limits and revisioned overwrites', function (): void {
    config(['youpanel.files.max_upload_bytes' => 20]);
    [$user, $website, $root] = workspaceFixture();

    $this->actingAs($user)
        ->post("/api/v1/websites/{$website->id}/files/upload", ['allowed_path_id' => $root->id, 'file' => UploadedFile::fake()->createWithContent('huge.txt', str_repeat('a', 50))])
        ->assertConflict();

    $this->actingAs($user)
        ->post("/api/v1/websites/{$website->id}/files/upload", ['allowed_path_id' => $root->id, 'file' => UploadedFile::fake()->createWithContent('README.md', 'new'), 'overwrite' => true])
        ->assertCreated();

    expect(FileRevision::query()->where('operation', 'upload-overwrite')->exists())->toBeTrue();
});

it('moves deletes to trash and restores without silent overwrite', function (): void {
    [$user, $website, $root, $path] = workspaceFixture();

    $this->actingAs($user)
        ->deleteJson("/api/v1/websites/{$website->id}/files", ['allowed_path_id' => $root->id, 'path' => 'README.md'])
        ->assertOk();

    $entry = TrashEntry::query()->firstOrFail();
    expect(file_exists($path.'/README.md'))->toBeFalse();

    file_put_contents($path.'/README.md', 'replacement');
    $this->actingAs($user)
        ->postJson("/api/v1/websites/{$website->id}/trash/{$entry->id}/restore")
        ->assertConflict();

    unlink($path.'/README.md');
    $this->actingAs($user)
        ->postJson("/api/v1/websites/{$website->id}/trash/{$entry->id}/restore")
        ->assertOk();
});

it('keeps permanent deletion owner only', function (): void {
    [$user, $website, $root] = workspaceFixture();
    $this->actingAs($user)->deleteJson("/api/v1/websites/{$website->id}/files", ['allowed_path_id' => $root->id, 'path' => 'README.md']);
    $entry = TrashEntry::query()->firstOrFail();
    $owner = User::factory()->owner()->create(['password' => Hash::make('CorrectPassword!123')]);

    $this->actingAs($user)->deleteJson("/api/v1/websites/{$website->id}/trash/{$entry->id}")->assertForbidden();
    $this->actingAs($owner)->deleteJson("/api/v1/websites/{$website->id}/trash/{$entry->id}", ['password' => 'wrong'])->assertForbidden();
    $this->actingAs($owner)->deleteJson("/api/v1/websites/{$website->id}/trash/{$entry->id}", ['password' => 'CorrectPassword!123'])->assertOk();
});

it('rejects symlink escapes when supported', function (): void {
    [$user, $website, $root, $path] = workspaceFixture();
    $outside = storage_path('app/outside-'.Str::uuid());
    File::ensureDirectoryExists($outside);
    file_put_contents($outside.'/secret.txt', 'secret');

    if (! @symlink($outside, $path.'/escape')) {
        $this->markTestSkipped('Symlink creation is not available on this platform.');
    }

    $this->actingAs($user)
        ->getJson("/api/v1/websites/{$website->id}/files?allowed_path_id={$root->id}&path=escape")
        ->assertStatus(422);
});

it('rejects archive traversal entries when zip is available', function (): void {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive is not available.');
    }

    $zipPath = storage_path('app/test-workspaces/'.Str::uuid().'.zip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('../evil.txt', 'bad');
    $zip->close();

    app(ArchiveService::class)->validateZip($zipPath);
})->throws(InvalidWorkspacePathException::class);
