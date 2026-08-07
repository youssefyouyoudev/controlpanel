<?php

namespace App\Services;

use App\Models\AllowedPath;
use App\Models\FileRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FileRevisionService
{
    public function createSnapshot(Website $website, AllowedPath $allowedPath, ?User $user, string $relativePath, string $operation, string $absolutePath, ?string $newChecksum = null, ?int $newSize = null): FileRevision
    {
        $size = is_file($absolutePath) ? filesize($absolutePath) : null;
        $checksum = is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null;
        $storagePath = null;

        if (is_file($absolutePath) && $size !== false && $size <= (int) config('youpanel.files.revision_max_bytes')) {
            $storagePath = 'file-revisions/'.$website->id.'/'.Str::uuid().'.snapshot';
            $target = storage_path('app/private/'.$storagePath);
            File::ensureDirectoryExists(dirname($target));
            copy($absolutePath, $target);
        }

        $revision = FileRevision::query()->create([
            'website_id' => $website->id,
            'allowed_path_id' => $allowedPath->id,
            'user_id' => $user?->id,
            'relative_path' => $relativePath,
            'relative_path_hash' => hash('sha256', $relativePath),
            'operation' => $operation,
            'original_size' => $size === false ? null : $size,
            'new_size' => $newSize,
            'original_checksum' => $checksum,
            'new_checksum' => $newChecksum,
            'storage_path' => $storagePath,
            'metadata' => ['snapshot_stored' => $storagePath !== null],
        ]);

        $this->prune($website, $allowedPath, $relativePath);

        return $revision;
    }

    private function prune(Website $website, AllowedPath $allowedPath, string $relativePath): void
    {
        $max = (int) config('youpanel.files.revisions_per_file');
        $expiredBefore = now()->subDays((int) config('youpanel.files.revision_retention_days'));

        $old = FileRevision::query()
            ->whereBelongsTo($website)
            ->whereBelongsTo($allowedPath)
            ->where('relative_path_hash', hash('sha256', $relativePath))
            ->where(function ($query) use ($max, $expiredBefore): void {
                $query->where('created_at', '<', $expiredBefore)
                    ->orWhereNotIn('id', FileRevision::query()->latest('created_at')->limit($max)->select('id'));
            })
            ->get();

        foreach ($old as $revision) {
            if ($revision->storage_path) {
                @unlink(storage_path('app/private/'.$revision->storage_path));
            }
            $revision->delete();
        }
    }
}
