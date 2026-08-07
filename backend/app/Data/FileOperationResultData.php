<?php

namespace App\Data;

class FileOperationResultData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $message,
        public readonly ?string $relativePath = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'relative_path' => $this->relativePath,
            'metadata' => $this->metadata,
        ];
    }
}
