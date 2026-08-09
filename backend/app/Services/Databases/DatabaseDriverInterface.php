<?php

namespace App\Services\Databases;

interface DatabaseDriverInterface
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function databases(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tables(string $database): array;

    /**
     * @return array<string, mixed>
     */
    public function table(string $database, string $table): array;

    /**
     * @return array<string, mixed>
     */
    public function rows(string $database, string $table, int $page, int $perPage): array;

    /**
     * @return array<string, mixed>
     */
    public function execute(string $database, string $sql, int $limit): array;

    /**
     * @return array<string, mixed>
     */
    public function securityDiagnostics(): array;
}
