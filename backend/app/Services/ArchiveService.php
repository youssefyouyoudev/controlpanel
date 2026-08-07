<?php

namespace App\Services;

use App\Data\FileOperationResultData;
use App\Data\ResolvedWorkspacePathData;
use App\Exceptions\FileConflictException;
use App\Exceptions\InvalidWorkspacePathException;
use ZipArchive;

class ArchiveService
{
    public function createZip(ResolvedWorkspacePathData $source): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new FileConflictException('ZIP support is not available on this PHP installation.');
        }

        $zipPath = storage_path('app/private/downloads/'.uniqid('archive-', true).'.zip');
        @mkdir(dirname($zipPath), 0755, true);
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($source->type === 'file') {
            $zip->addFile($source->absolutePath, basename($source->relativePath));
        } else {
            $count = 0;
            $total = 0;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source->absolutePath, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isLink() || ! $file->isFile()) {
                    continue;
                }
                $count++;
                $total += $file->getSize();
                if ($count > (int) config('youpanel.files.archive_max_files') || $total > (int) config('youpanel.files.archive_max_uncompressed_bytes')) {
                    $zip->close();
                    @unlink($zipPath);
                    throw new FileConflictException('Archive limits were exceeded.');
                }
                $zip->addFile($file->getPathname(), ltrim(str_replace($source->absolutePath, '', $file->getPathname()), DIRECTORY_SEPARATOR));
            }
        }

        $zip->close();

        return $zipPath;
    }

    public function validateZip(string $archivePath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new FileConflictException('ZIP support is not available on this PHP installation.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new FileConflictException('The archive could not be opened.');
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');
            $normalized = str_replace('\\', '/', $name);

            if ($zip->numFiles > (int) config('youpanel.files.archive_max_files')) {
                throw new FileConflictException('The archive contains too many files.');
            }

            if ($normalized === '' || str_contains($normalized, "\0") || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1 || str_contains($normalized, '../') || str_contains('/'.$normalized, '/../')) {
                throw InvalidWorkspacePathException::forUser('The archive contains unsafe paths.');
            }

            if (method_exists($zip, 'getExternalAttributesIndex')) {
                $attributes = 0;
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if (((($attributes >> 16) & 0170000) === 0120000)) {
                    throw InvalidWorkspacePathException::forUser('The archive contains symbolic links, which are not allowed.');
                }
            }

            $total += (int) ($stat['size'] ?? 0);
            if ($total > (int) config('youpanel.files.archive_max_uncompressed_bytes')) {
                throw new FileConflictException('The archive is too large to extract safely.');
            }
        }

        $zip->close();
    }

    public function extractZip(string $archivePath, ResolvedWorkspacePathData $destination, bool $overwrite = false): FileOperationResultData
    {
        $this->validateZip($archivePath);

        $zip = new ZipArchive;
        $zip->open($archivePath);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $target = $destination->absolutePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (! $overwrite && file_exists($target)) {
                $zip->close();
                throw new FileConflictException('Archive extraction would overwrite an existing file.');
            }
        }
        $zip->extractTo($destination->absolutePath);
        $zip->close();

        return new FileOperationResultData('Archive extracted.', $destination->relativePath);
    }
}
