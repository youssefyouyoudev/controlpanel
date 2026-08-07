<?php

namespace App\Services\Operations;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Exceptions\OperationBlockedException;
use App\Jobs\RunBackupJob;
use App\Models\AllowedPath;
use App\Models\Backup;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Notifications\YouPanelNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use ZipArchive;

class BackupService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function request(Website $website, User $user, BackupType $type, ?WebsiteComponent $component = null): Backup
    {
        if (! $user->can('view', $website) || ! in_array($user->role->value, ['owner', 'developer'], true)) {
            throw new OperationBlockedException('Your role cannot create backups.');
        }

        $backup = Backup::query()->create([
            'website_id' => $website->id,
            'website_component_id' => $component?->id,
            'type' => $type,
            'status' => BackupStatus::Queued,
            'requested_by' => $user->id,
            'storage_disk' => (string) config('youpanel.backups.storage_disk'),
            'expires_at' => now()->addDays((int) config('youpanel.backups.retention_days')),
        ]);

        $this->auditLogger->record('backup.requested', $user, $website, ['target_type' => 'backup', 'target_identifier' => $backup->uuid]);
        RunBackupJob::dispatch($backup->id);

        return $backup;
    }

    public function run(Backup $backup): void
    {
        try {
            $backup->update(['status' => BackupStatus::Running, 'started_at' => now()]);
            if ($backup->type !== BackupType::Files && $backup->type !== BackupType::Manual && $backup->type !== BackupType::Full) {
                throw new OperationBlockedException('Only file-style backups are implemented in Phase 3 local mode.');
            }

            $path = $this->createFileBackup($backup);
            $absolute = storage_path('app/private/'.$path);
            $backup->update([
                'status' => BackupStatus::Succeeded,
                'finished_at' => now(),
                'storage_path' => $path,
                'size_bytes' => filesize($absolute) ?: null,
                'checksum' => hash_file('sha256', $absolute),
                'metadata' => ['verified_readable' => is_readable($absolute)],
            ]);
            $this->auditLogger->record('backup.created', $backup->requester, $backup->website, ['target_type' => 'backup', 'target_identifier' => $backup->uuid]);
            Notification::send($backup->website->members()->get()->push($backup->requester)->unique('id'), new YouPanelNotification('Backup completed', 'A backup was stored in private YouPanel storage.', 'success', '/backups/'.$backup->uuid));
        } catch (\Throwable $exception) {
            $backup->update(['status' => BackupStatus::Failed, 'finished_at' => now(), 'error_message' => $exception->getMessage()]);
            $this->auditLogger->record('backup.failed', $backup->requester, $backup->website, ['target_type' => 'backup', 'target_identifier' => $backup->uuid, 'reason' => $exception->getMessage()]);
        }
    }

    public function verify(Backup $backup): bool
    {
        $absolute = storage_path('app/private/'.(string) $backup->storage_path);

        return $backup->storage_path && is_file($absolute) && hash_file('sha256', $absolute) === $backup->checksum;
    }

    public function stageRestore(Backup $backup, User $user, string $typedWebsiteName, string $password): string
    {
        if (! $user->isOwner() || $typedWebsiteName !== $backup->website->name || ! Hash::check($password, (string) $user->password)) {
            throw new OperationBlockedException('Restore requires owner password confirmation and the exact website name.');
        }

        if (! $this->verify($backup)) {
            throw new OperationBlockedException('Backup checksum verification failed.');
        }

        $stage = 'restore-staging/'.$backup->website_id.'/'.$backup->uuid.'-'.Str::uuid();
        File::ensureDirectoryExists(storage_path('app/private/'.$stage));
        $backup->update(['status' => BackupStatus::Restoring, 'metadata' => ['staging_path' => $stage]]);
        $this->auditLogger->record('restore.requested', $user, $backup->website, ['target_type' => 'backup', 'target_identifier' => $backup->uuid, 'staged_only' => true]);

        return $stage;
    }

    public function pruneExpired(): int
    {
        $deleted = 0;
        Backup::query()->where('expires_at', '<', now())->whereNotNull('storage_path')->each(function (Backup $backup) use (&$deleted): void {
            if (! str_starts_with((string) $backup->storage_path, 'backups/')) {
                return;
            }
            File::delete(storage_path('app/private/'.$backup->storage_path));
            $backup->delete();
            $deleted++;
        });

        return $deleted;
    }

    private function createFileBackup(Backup $backup): string
    {
        $root = AllowedPath::query()->whereBelongsTo($backup->website)->where('is_active', true)->orderByDesc('is_primary')->first();
        if (! $root || ! is_dir($root->absolute_path)) {
            throw new OperationBlockedException('No available approved root exists for backup.');
        }

        $path = 'backups/'.$backup->website_id.'/'.$backup->uuid.'.zip';
        $absolute = storage_path('app/private/'.$path);
        File::ensureDirectoryExists(dirname($absolute));

        if ((bool) config('youpanel-actions.mock')) {
            File::put($absolute, "Mock backup. No production files were read.\n");

            return $path;
        }

        if (! class_exists(ZipArchive::class)) {
            throw new OperationBlockedException('ZIP support is not available.');
        }

        $zip = new ZipArchive;
        $zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->addDirectory($zip, $root->absolute_path, $root->absolute_path);
        $zip->close();

        if (! is_file($absolute) || filesize($absolute) === 0) {
            throw new OperationBlockedException('Backup archive was not created.');
        }

        return $path;
    }

    private function addDirectory(ZipArchive $zip, string $root, string $directory): void
    {
        $excluded = config('youpanel.backups.exclude', []);
        foreach (new \DirectoryIterator($directory) as $item) {
            if ($item->isDot() || $item->isLink() || in_array($item->getFilename(), $excluded, true)) {
                continue;
            }

            $relative = ltrim(str_replace($root, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            if ($item->isDir()) {
                $this->addDirectory($zip, $root, $item->getPathname());
            } elseif ($item->isFile()) {
                $zip->addFile($item->getPathname(), str_replace(DIRECTORY_SEPARATOR, '/', $relative));
            }
        }
    }
}
