<?php

namespace App\Data\Coolify;

class CoolifyCapabilityData
{
    public function __construct(
        public readonly string $capability,
        public readonly bool $supported,
        public readonly ?string $endpoint,
        public readonly string $permission,
        public readonly bool $implemented,
        public readonly string $fallback,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'capability' => $this->capability,
            'supported' => $this->supported,
            'endpoint' => $this->endpoint,
            'permission' => $this->permission,
            'implemented' => $this->implemented,
            'fallback' => $this->fallback,
        ];
    }
}
