<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\Operations\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $backupId) {}

    public function handle(BackupService $backups): void
    {
        $backups->run(Backup::query()->with(['website', 'component', 'requester'])->findOrFail($this->backupId));
    }
}
