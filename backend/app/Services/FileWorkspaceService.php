<?php

namespace App\Services;

use App\Contracts\FileWorkspaceInterface;
use App\Data\FileContentData;
use App\Data\FileEntryData;
use App\Data\FileOperationResultData;
use App\Exceptions\FileConflictException;
use App\Exceptions\InvalidWorkspacePathException;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class FileWorkspaceService implements FileWorkspaceInterface
{
    public function __construct(
        private readonly SecurePathResolver $resolver,
        private readonly FileRevisionService $revisions,
        private readonly TrashService $trash,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(Website $website, User $user, int $allowedPathId, string $path = '', string $sort = 'name'): array
    {
        $resolved = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'list');

        if ($resolved->type !== 'dir') {
            throw InvalidWorkspacePathException::forUser('Only folders can be listed.');
        }

        $entries = [];
        $items = @scandir($resolved->absolutePath) ?: [];
        $limit = (int) config('youpanel.files.max_directory_items');

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $relative = trim($resolved->relativePath.'/'.$item, '/');

            if (! $user->isOwner() && (str_starts_with($item, '.') || $this->resolver->isProtected($relative))) {
                continue;
            }

            $absolute = $resolved->absolutePath.DIRECTORY_SEPARATOR.$item;
            $type = is_dir($absolute) ? 'directory' : 'file';
            $size = is_file($absolute) ? filesize($absolute) : null;
            $entries[] = (new FileEntryData(
                name: $item,
                relativePath: $relative,
                type: $type,
                size: $size === false ? null : $size,
                modifiedAt: filemtime($absolute) ? date(DATE_ATOM, filemtime($absolute)) : null,
                readable: is_readable($absolute),
                writable: is_writable($absolute),
                editable: is_file($absolute) && $this->isEditable($absolute, $relative),
                protected: $this->resolver->isProtected($relative),
            ))->toArray();

            if (count($entries) >= $limit) {
                break;
            }
        }

        usort($entries, fn (array $a, array $b): int => match ($sort) {
            'modified' => strcmp((string) ($b['modifiedAt'] ?? ''), (string) ($a['modifiedAt'] ?? '')),
            'size' => ((int) ($a['size'] ?? 0)) <=> ((int) ($b['size'] ?? 0)),
            default => strcmp((string) $a['name'], (string) $b['name']),
        });

        return $entries;
    }

    public function read(Website $website, User $user, int $allowedPathId, string $path): FileContentData
    {
        $resolved = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'read');

        if ($resolved->type !== 'file') {
            throw InvalidWorkspacePathException::forUser('Only files can be opened.');
        }

        $size = filesize($resolved->absolutePath);
        $size = $size === false ? 0 : $size;

        if (! $this->isEditable($resolved->absolutePath, $resolved->relativePath)) {
            throw InvalidWorkspacePathException::forUser($size > (int) config('youpanel.files.max_edit_bytes')
                ? 'This file is too large for browser editing. Download it instead.'
                : 'Binary files cannot be opened in the editor.');
        }

        $content = file_get_contents($resolved->absolutePath);
        if ($content === false) {
            throw InvalidWorkspacePathException::forUser('The file could not be read.');
        }

        $this->auditLogger->record('file.opened', $user, $website, ['target_type' => 'file', 'target_identifier' => $resolved->relativePath]);

        return new FileContentData(
            relativePath: $resolved->relativePath,
            language: $this->languageFor($resolved->relativePath),
            encoding: 'utf-8',
            size: $size,
            modifiedAt: filemtime($resolved->absolutePath) ? date(DATE_ATOM, filemtime($resolved->absolutePath)) : null,
            checksum: hash_file('sha256', $resolved->absolutePath),
            content: $content,
            readOnlyReason: $resolved->allowedPath->can_write ? null : 'You have read-only access to this file.',
        );
    }

    public function save(Website $website, User $user, int $allowedPathId, string $path, string $content, string $checksum): FileOperationResultData
    {
        $resolved = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'save');

        if ($resolved->type !== 'file') {
            throw InvalidWorkspacePathException::forUser('Only files can be saved.');
        }

        $currentChecksum = hash_file('sha256', $resolved->absolutePath);
        if (! hash_equals($currentChecksum, $checksum)) {
            throw new FileConflictException('This file changed on the server after you opened it.', [
                'current_checksum' => $currentChecksum,
                'relative_path' => $resolved->relativePath,
            ]);
        }

        $newChecksum = hash('sha256', $content);
        $this->revisions->createSnapshot($website, $resolved->allowedPath, $user, $resolved->relativePath, 'save', $resolved->absolutePath, $newChecksum, strlen($content));
        $this->atomicWrite($resolved->absolutePath, $content);
        $this->auditLogger->record('file.saved', $user, $website, ['target_type' => 'file', 'target_identifier' => $resolved->relativePath]);

        return new FileOperationResultData('File saved.', $resolved->relativePath, ['checksum' => $newChecksum]);
    }

    public function createFile(Website $website, User $user, int $allowedPathId, string $path, ?string $content = null): FileOperationResultData
    {
        $resolved = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'create', true);

        if ($resolved->exists) {
            throw new FileConflictException('A file already exists at that path.');
        }

        $this->atomicWrite($resolved->absolutePath, $content ?? '');
        $this->auditLogger->record('file.created', $user, $website, ['target_type' => 'file', 'target_identifier' => $resolved->relativePath]);

        return new FileOperationResultData('File created.', $resolved->relativePath);
    }

    public function createDirectory(Website $website, User $user, int $allowedPathId, string $path): FileOperationResultData
    {
        $resolved = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'mkdir', true);

        if ($resolved->exists) {
            throw new FileConflictException('A folder already exists at that path.');
        }

        File::makeDirectory($resolved->absolutePath, 0755, false);
        $this->auditLogger->record('directory.created', $user, $website, ['target_type' => 'directory', 'target_identifier' => $resolved->relativePath]);

        return new FileOperationResultData('Folder created.', $resolved->relativePath);
    }

    public function upload(Website $website, User $user, int $allowedPathId, string $directory, UploadedFile $file, bool $overwrite = false): FileOperationResultData
    {
        $max = min((int) config('youpanel.files.max_upload_bytes'), (int) ($website->allowedPaths()->findOrFail($allowedPathId)->max_upload_bytes ?: config('youpanel.files.max_upload_bytes')));
        if ($file->getSize() > $max) {
            throw new FileConflictException('This upload exceeds the configured limit.');
        }

        $filename = $this->sanitizeFilename($file->getClientOriginalName());
        $targetPath = trim($directory.'/'.$filename, '/');
        $resolved = $this->resolver->resolve($website, $user, $allowedPathId, $targetPath, 'upload', true);

        if ($resolved->exists && ! $overwrite) {
            throw new FileConflictException('Upload would overwrite an existing file.');
        }

        if ($resolved->exists) {
            $this->revisions->createSnapshot($website, $resolved->allowedPath, $user, $resolved->relativePath, 'upload-overwrite', $resolved->absolutePath);
        }

        $file->move(dirname($resolved->absolutePath), basename($resolved->absolutePath));
        $this->auditLogger->record('file.uploaded', $user, $website, ['target_type' => 'file', 'target_identifier' => $resolved->relativePath]);

        return new FileOperationResultData('File uploaded.', $resolved->relativePath);
    }

    public function trash(Website $website, User $user, int $allowedPathId, string $path): FileOperationResultData
    {
        $resolved = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'trash');
        $result = $this->trash->trash($resolved, $user);
        $this->auditLogger->record('file.trashed', $user, $website, ['target_type' => $resolved->type, 'target_identifier' => $resolved->relativePath]);

        return $result;
    }

    public function rename(Website $website, User $user, int $allowedPathId, string $path, string $name): FileOperationResultData
    {
        $source = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'rename');
        $target = $this->resolver->resolve($website, $user, $allowedPathId, trim(dirname($source->relativePath).'/'.$this->sanitizeFilename($name), './'), 'rename', true);

        if ($target->exists) {
            throw new FileConflictException('Rename would overwrite an existing item.');
        }

        rename($source->absolutePath, $target->absolutePath);
        $this->auditLogger->record('file.renamed', $user, $website, ['target_type' => $source->type, 'target_identifier' => $source->relativePath, 'new_path' => $target->relativePath]);

        return new FileOperationResultData('Item renamed.', $target->relativePath);
    }

    public function copy(Website $website, User $user, int $allowedPathId, string $path, string $destination): FileOperationResultData
    {
        $source = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'copy');
        $target = $this->resolver->resolve($website, $user, $allowedPathId, $destination, 'copy', true);
        if ($target->exists) {
            throw new FileConflictException('Copy would overwrite an existing item.');
        }
        $source->type === 'dir' ? File::copyDirectory($source->absolutePath, $target->absolutePath) : copy($source->absolutePath, $target->absolutePath);
        $this->auditLogger->record('file.copied', $user, $website, ['target_type' => $source->type, 'target_identifier' => $source->relativePath, 'new_path' => $target->relativePath]);

        return new FileOperationResultData('Item copied.', $target->relativePath);
    }

    public function move(Website $website, User $user, int $allowedPathId, string $path, string $destination): FileOperationResultData
    {
        $source = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'move');
        $target = $this->resolver->resolve($website, $user, $allowedPathId, $destination, 'move', true);
        if ($target->exists || ($source->type === 'dir' && str_starts_with($target->absolutePath, $source->absolutePath.DIRECTORY_SEPARATOR))) {
            throw new FileConflictException('Move destination is not safe.');
        }
        rename($source->absolutePath, $target->absolutePath);
        $this->auditLogger->record('file.moved', $user, $website, ['target_type' => $source->type, 'target_identifier' => $source->relativePath, 'new_path' => $target->relativePath]);

        return new FileOperationResultData('Item moved.', $target->relativePath);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(Website $website, User $user, int $allowedPathId, string $query, string $path = ''): array
    {
        $resolved = $this->resolver->resolve($website, $user, $allowedPathId, $path, 'search');
        $results = [];
        $limit = (int) config('youpanel.files.max_search_results');
        $ignored = ['.git', 'node_modules', 'vendor', '.next', 'storage/logs', 'storage/framework'];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($resolved->absolutePath, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $relative = trim($resolved->relativePath.'/'.str_replace($resolved->absolutePath, '', $item->getPathname()), '/\\');
            $relative = str_replace('\\', '/', $relative);
            if (collect($ignored)->contains(fn (string $ignoredPath): bool => str_contains($relative, $ignoredPath))) {
                continue;
            }
            if (stripos($item->getFilename(), $query) !== false && ! $this->resolver->isProtected($relative)) {
                $results[] = ['name' => $item->getFilename(), 'relative_path' => $relative, 'type' => $item->isDir() ? 'directory' : 'file'];
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    public function isEditable(string $absolutePath, string $relativePath): bool
    {
        $size = filesize($absolutePath);
        if ($size === false || $size > (int) config('youpanel.files.max_edit_bytes')) {
            return false;
        }

        $handle = fopen($absolutePath, 'rb');
        $sample = $handle ? fread($handle, 8192) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }

        if ($sample === false || str_contains($sample, "\0")) {
            return false;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $name = basename($relativePath);
        $editable = ['php', 'js', 'jsx', 'ts', 'tsx', 'json', 'css', 'scss', 'html', 'xml', 'yaml', 'yml', 'md', 'txt', 'conf', 'ini', 'toml', 'sh', 'sql', 'vue', 'svelte'];

        return in_array($extension, $editable, true) || str_ends_with($name, '.blade.php') || $name === '.env.example';
    }

    public function languageFor(string $relativePath): string
    {
        $name = basename($relativePath);
        if (str_ends_with($name, '.blade.php')) {
            return 'php';
        }

        return match (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION))) {
            'js', 'jsx' => 'javascript',
            'ts', 'tsx' => 'typescript',
            'json' => 'json',
            'css', 'scss' => 'css',
            'md' => 'markdown',
            'yaml', 'yml' => 'yaml',
            'html', 'xml' => 'html',
            'php' => 'php',
            'sql' => 'sql',
            default => 'plaintext',
        };
    }

    private function atomicWrite(string $absolutePath, string $content): void
    {
        $temporary = dirname($absolutePath).DIRECTORY_SEPARATOR.'.youpanel-'.uniqid('', true).'.tmp';
        $handle = fopen($temporary, 'wb');
        if (! $handle) {
            throw new FileConflictException('The server account cannot write this folder.');
        }

        try {
            flock($handle, LOCK_EX);
            fwrite($handle, $content);
            fflush($handle);
            flock($handle, LOCK_UN);
            fclose($handle);
            if (PHP_OS_FAMILY === 'Windows' && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
            rename($temporary, $absolutePath);
        } catch (\Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($temporary);
            throw $exception;
        }
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || str_contains($filename, "\0") || in_array($filename, ['.', '..'], true)) {
            throw InvalidWorkspacePathException::forUser('The filename is invalid.');
        }

        return $filename;
    }
}
