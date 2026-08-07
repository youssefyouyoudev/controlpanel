<?php

namespace App\Services;

use App\Data\ResolvedWorkspacePathData;
use App\Enums\UserRole;
use App\Exceptions\InvalidWorkspacePathException;
use App\Exceptions\WorkspacePermissionException;
use App\Models\AllowedPath;
use App\Models\User;
use App\Models\Website;

class SecurePathResolver
{
    /**
     * @var array<int, string>
     */
    private array $protectedPatterns = ['.env', '.env.*', '*.pem', '*.key', 'id_rsa', 'id_ed25519', 'authorized_keys', 'credentials*', 'secrets*'];

    public function resolve(Website $website, User $user, int $allowedPathId, string $path, string $operation, bool $forCreation = false): ResolvedWorkspacePathData
    {
        $allowedPath = AllowedPath::query()
            ->whereBelongsTo($website)
            ->whereKey($allowedPathId)
            ->where('is_active', true)
            ->firstOrFail();

        if (! $user->can('view', $website)) {
            throw WorkspacePermissionException::denied();
        }

        $this->assertOperationAllowed($user, $allowedPath, $operation);

        $rootPath = realpath($allowedPath->absolute_path);
        if ($rootPath === false || ! is_dir($rootPath) || ! is_readable($rootPath)) {
            throw InvalidWorkspacePathException::forUser('This approved root is unavailable.');
        }

        $relativePath = $this->normalizeRelativePath($path);
        $this->assertProtectedAccess($user, $relativePath, $operation);
        $this->assertRules($allowedPath, $relativePath);

        $candidate = $relativePath === '' ? $rootPath : $rootPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $existingTarget = realpath($candidate);
        $parentCandidate = dirname($candidate);
        $existingParent = realpath($forCreation || $existingTarget === false ? $parentCandidate : dirname($candidate));

        if ($existingParent === false) {
            throw InvalidWorkspacePathException::forUser('The destination parent folder does not exist.');
        }

        $canonical = $existingTarget !== false ? $existingTarget : $candidate;
        $boundaryPath = $existingTarget !== false ? $existingTarget : $existingParent;

        $this->assertInsideRoot($rootPath, $boundaryPath);
        $this->assertNoEscapingSymlink($rootPath, $relativePath);

        if ($existingTarget !== false) {
            $type = filetype($existingTarget) ?: null;
            if (! in_array($type, ['file', 'dir'], true)) {
                throw InvalidWorkspacePathException::forUser('Special files cannot be opened in YouPanel.');
            }
        }

        return new ResolvedWorkspacePathData(
            website: $website,
            allowedPath: $allowedPath,
            rootPath: $rootPath,
            relativePath: $relativePath,
            absolutePath: $canonical,
            parentPath: $existingParent,
            exists: $existingTarget !== false,
            type: $existingTarget !== false ? (filetype($existingTarget) ?: null) : null,
        );
    }

    public function normalizeRelativePath(string $path): string
    {
        $decoded = rawurldecode($path);

        if (str_contains($decoded, "\0")) {
            throw InvalidWorkspacePathException::forUser('The requested path is invalid.');
        }

        $normalized = str_replace('\\', '/', trim($decoded));

        if ($normalized === '' || $normalized === '.') {
            return '';
        }

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            throw InvalidWorkspacePathException::forUser('Absolute paths are not accepted.');
        }

        $segments = array_values(array_filter(explode('/', $normalized), fn (string $segment): bool => $segment !== ''));

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw InvalidWorkspacePathException::forUser('Path traversal is not allowed.');
            }
        }

        return implode('/', $segments);
    }

    public function assertInsideRoot(string $rootPath, string $candidate): void
    {
        $root = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $path = rtrim($candidate, DIRECTORY_SEPARATOR);

        if ($path !== $root && ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
            throw InvalidWorkspacePathException::forUser();
        }
    }

    public function isProtected(string $relativePath): bool
    {
        $name = basename($relativePath);

        foreach ($this->protectedPatterns as $pattern) {
            if ($pattern === '.env.*' && $name === '.env.example') {
                continue;
            }

            if (fnmatch($pattern, $name, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }

    private function assertOperationAllowed(User $user, AllowedPath $allowedPath, string $operation): void
    {
        if (! $allowedPath->operationEnabled($operation)) {
            throw WorkspacePermissionException::denied('This operation is disabled for the selected root.');
        }

        if (in_array($operation, ['write', 'save', 'upload', 'create', 'mkdir', 'rename', 'move', 'copy', 'delete', 'trash', 'extract'], true)
            && ! $user->role->canModifyAssignedWebsite()) {
            throw WorkspacePermissionException::denied('You have read-only access to this file.');
        }

        if (in_array($operation, ['permanent-delete', 'configure-root'], true) && $user->role !== UserRole::Owner) {
            throw WorkspacePermissionException::denied('Only owners may perform this sensitive operation.');
        }
    }

    private function assertProtectedAccess(User $user, string $relativePath, string $operation): void
    {
        if ($relativePath === '' || ! $this->isProtected($relativePath)) {
            return;
        }

        if (! $user->isOwner() || in_array($operation, ['write', 'save', 'upload'], true)) {
            throw WorkspacePermissionException::denied('This protected file is restricted.');
        }
    }

    private function assertRules(AllowedPath $allowedPath, string $relativePath): void
    {
        $name = basename($relativePath);

        foreach ($allowedPath->blocked_patterns ?? [] as $pattern) {
            if (fnmatch((string) $pattern, $name, FNM_CASEFOLD)) {
                throw WorkspacePermissionException::denied('This file is blocked by the root policy.');
            }
        }

        $extensions = $allowedPath->allowed_extensions;
        if ($relativePath !== '' && is_array($extensions) && $extensions !== []) {
            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
            $allowed = array_map(fn (mixed $item): string => strtolower(ltrim((string) $item, '.')), $extensions);

            if ($extension !== '' && ! in_array($extension, $allowed, true)) {
                throw WorkspacePermissionException::denied('This file extension is not allowed in this root.');
            }
        }
    }

    private function assertNoEscapingSymlink(string $rootPath, string $relativePath): void
    {
        if ($relativePath === '') {
            return;
        }

        $cursor = $rootPath;
        foreach (explode('/', $relativePath) as $segment) {
            $cursor .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($cursor)) {
                $target = realpath($cursor);
                if ($target === false) {
                    throw InvalidWorkspacePathException::forUser('Broken symbolic links are not allowed.');
                }

                $this->assertInsideRoot($rootPath, $target);
            }
        }
    }
}
