<?php

namespace App\Jobs;

use App\Models\Deployment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;

class PruneDeploymentLogsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Deployment::query()
            ->whereNotNull('logs_storage_path')
            ->where('created_at', '<', now()->subDays((int) config('coolify.log_retention_days')))
            ->get()
            ->each(function (Deployment $deployment): void {
                $absolute = storage_path('app/private/'.$deployment->logs_storage_path);
                if (str_starts_with(realpath(dirname($absolute)) ?: '', storage_path('app/private/deployment-output')) && File::exists($absolute)) {
                    File::delete($absolute);
                }

                $deployment->update(['logs_storage_path' => null]);
            });
    }
}
