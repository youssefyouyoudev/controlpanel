<?php

namespace App\Services\Discovery;

class StorageInspector
{
    public function directorySize(?string $root): ?int
    {
        if ($root === null || ! is_dir($root) || ! is_readable($root)) {
            return null;
        }

        $size = 0;
        $count = 0;
        $limit = (int) config('youpanel.discovery.directory_size_max_items', 5000);
        $ignored = (array) config('youpanel.discovery.size_exclude', []);
        $directory = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator($directory, function (\SplFileInfo $item) use ($root, $ignored): bool {
            $relative = str_replace('\\', '/', str_replace(rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, '', $item->getPathname()));

            foreach ($ignored as $path) {
                $path = trim((string) $path, '/');
                if ($relative === $path || str_starts_with($relative, $path.'/')) {
                    return false;
                }
            }

            return true;
        });
        $iterator = new \RecursiveIteratorIterator($filter);

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }

            if (++$count >= $limit) {
                break;
            }
        }

        return $size;
    }
}
