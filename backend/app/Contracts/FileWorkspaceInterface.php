<?php

namespace App\Contracts;

use App\Data\FileContentData;
use App\Data\FileOperationResultData;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\UploadedFile;

interface FileWorkspaceInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(Website $website, User $user, int $allowedPathId, string $path = '', string $sort = 'name'): array;

    public function read(Website $website, User $user, int $allowedPathId, string $path): FileContentData;

    public function save(Website $website, User $user, int $allowedPathId, string $path, string $content, string $checksum): FileOperationResultData;

    public function createFile(Website $website, User $user, int $allowedPathId, string $path, ?string $content = null): FileOperationResultData;

    public function createDirectory(Website $website, User $user, int $allowedPathId, string $path): FileOperationResultData;

    public function upload(Website $website, User $user, int $allowedPathId, string $directory, UploadedFile $file, bool $overwrite = false): FileOperationResultData;
}
