<?php

namespace App\Jobs;

use App\Services\Operations\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneBackupRetentionJob implements ShouldQueue
{
    use Queueable;

    public function handle(BackupService $backups): void
    {
        $backups->pruneExpired();
    }
}
