<?php

namespace App\Console\Commands;

use App\Models\TrashEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PruneExpiredTrashCommand extends Command
{
    protected $signature = 'youpanel:prune-expired-trash {--dry-run : Show what would be deleted without deleting files}';

    protected $description = 'Delete expired YouPanel trash entries from private storage.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $entries = TrashEntry::query()
            ->whereNull('restored_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($entries as $entry) {
            if (! str_starts_with($entry->trash_storage_path, 'trash/')) {
                $this->warn("Skipped unexpected trash path for entry {$entry->id}.");

                continue;
            }

            $path = storage_path('app/private/'.$entry->trash_storage_path);
            $this->line(($dryRun ? 'Would delete ' : 'Deleting ').$entry->trash_storage_path);

            if (! $dryRun) {
                if (is_dir($path)) {
                    File::deleteDirectory($path);
                } else {
                    File::delete($path);
                }

                $entry->delete();
            }
        }

        $this->info(($dryRun ? 'Matched ' : 'Pruned ').$entries->count().' expired trash entries.');

        return self::SUCCESS;
    }
}
