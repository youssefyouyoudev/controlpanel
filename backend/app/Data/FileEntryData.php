<?php

namespace App\Data;

class FileEntryData
{
    public function __construct(
        public readonly string $name,
        public readonly string $relativePath,
        public readonly string $type,
        public readonly ?int $size,
        public readonly ?string $modifiedAt,
        public readonly bool $readable,
        public readonly bool $writable,
        public readonly bool $editable,
        public readonly bool $protected,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
