<?php

namespace App\Services;

use App\Data\FileOperationResultData;
use App\Data\ResolvedWorkspacePathData;
use App\Exceptions\FileConflictException;
use App\Models\TrashEntry;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TrashService
{
    public function trash(ResolvedWorkspacePathData $path, User $user): FileOperationResultData
    {
        if (! $path->exists || $path->relativePath === '') {
            throw new FileConflictException('The approved root itself cannot be trashed.');
        }

        $trashPath = 'trash/'.$path->website->id.'/'.Str::uuid().'-'.basename($path->relativePath);
        $absoluteTrashPath = storage_path('app/private/'.$trashPath);
        File::ensureDirectoryExists(dirname($absoluteTrashPath));

        if (! @rename($path->absolutePath, $absoluteTrashPath)) {
            throw new FileConflictException('The item could not be moved to trash.');
        }

        TrashEntry::query()->create([
            'website_id' => $path->website->id,
            'allowed_path_id' => $path->allowedPath->id,
            'deleted_by' => $user->id,
            'original_relative_path' => $path->relativePath,
            'trash_storage_path' => $trashPath,
            'item_type' => $path->type === 'dir' ? 'directory' : 'file',
            'original_size' => $path->type === 'file' ? filesize($absoluteTrashPath) : null,
            'checksum' => $path->type === 'file' ? hash_file('sha256', $absoluteTrashPath) : null,
            'expires_at' => now()->addDays((int) config('youpanel.files.trash_retention_days')),
        ]);

        return new FileOperationResultData('Item moved to trash.', $path->relativePath);
    }

    public function restore(TrashEntry $entry, ResolvedWorkspacePathData $destination): FileOperationResultData
    {
        if ($destination->exists) {
            throw new FileConflictException('Restore would overwrite an existing file.', ['relative_path' => $destination->relativePath]);
        }

        $source = storage_path('app/private/'.$entry->trash_storage_path);
        if (! file_exists($source)) {
            throw new FileConflictException('The trash item is no longer available.');
        }

        if (! @rename($source, $destination->absolutePath)) {
            throw new FileConflictException('The item could not be restored.');
        }

        $entry->forceFill(['restored_at' => now()])->save();

        return new FileOperationResultData('Item restored.', $destination->relativePath);
    }
}
