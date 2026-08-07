<?php

namespace App\Services\Operations;

use App\Exceptions\InvalidWorkspacePathException;
use App\Exceptions\OperationBlockedException;
use App\Models\AllowedPath;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;

class OperationWorkspaceResolver
{
    public function resolve(Website $website, User $user, ?WebsiteComponent $component): string
    {
        if (! $user->can('view', $website)) {
            throw new OperationBlockedException('You cannot access this website.');
        }

        $root = AllowedPath::query()
            ->whereBelongsTo($website)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->first();

        if (! $root) {
            throw new OperationBlockedException('No approved file root is configured for this website.');
        }

        $rootPath = realpath($root->absolute_path);
        if ($rootPath === false || ! is_dir($rootPath)) {
            throw new OperationBlockedException('The approved file root is unavailable.');
        }

        $relative = $this->normalizeRelative($component?->relative_working_directory ?? '');
        $candidate = $relative === '' ? $rootPath : $rootPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $real = realpath($candidate);

        if ($real === false || ! is_dir($real)) {
            throw new OperationBlockedException('The component working directory is unavailable.');
        }

        $rootBoundary = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $realBoundary = rtrim($real, DIRECTORY_SEPARATOR);
        if ($realBoundary !== $rootBoundary && ! str_starts_with($realBoundary, $rootBoundary.DIRECTORY_SEPARATOR)) {
            throw InvalidWorkspacePathException::forUser('The component working directory escapes the approved root.');
        }

        return $real;
    }

    public function normalizeRelative(string $path): string
    {
        $normalized = str_replace('\\', '/', trim(rawurldecode($path)));

        if ($normalized === '' || $normalized === '.') {
            return '';
        }

        if (str_contains($normalized, "\0") || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            throw InvalidWorkspacePathException::forUser('Working directories must be relative to an approved root.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw InvalidWorkspacePathException::forUser('Working directory traversal is not allowed.');
            }
        }

        return trim($normalized, '/');
    }
}
