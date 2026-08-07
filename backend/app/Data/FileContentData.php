<?php

namespace App\Data;

class FileContentData
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $language,
        public readonly string $encoding,
        public readonly int $size,
        public readonly ?string $modifiedAt,
        public readonly string $checksum,
        public readonly string $content,
        public readonly ?string $readOnlyReason,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
