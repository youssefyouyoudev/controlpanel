<?php

namespace App\Data;

class ActionRunResultData
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $summary,
        public readonly bool $timedOut = false,
    ) {}
}
