<?php

namespace App\Data;

use App\Models\AllowedPath;
use App\Models\Website;

class ResolvedWorkspacePathData
{
    public function __construct(
        public readonly Website $website,
        public readonly AllowedPath $allowedPath,
        public readonly string $rootPath,
        public readonly string $relativePath,
        public readonly string $absolutePath,
        public readonly string $parentPath,
        public readonly bool $exists,
        public readonly ?string $type,
    ) {}
}
