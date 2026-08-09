<?php

namespace App\Services\Databases;

use App\Exceptions\OperationBlockedException;
use App\Models\User;
use App\Models\WebsiteDatabase;
use Illuminate\Support\Facades\Hash;

class DatabaseWorkbenchService
{
    public function __construct(private readonly DatabaseDriverInterface $driver) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            ...$this->driver->overview(),
            'security' => $this->driver->securityDiagnostics(),
            'website_links' => WebsiteDatabase::query()->with('website:id,name,domain')->latest()->get()->map(fn (WebsiteDatabase $database): array => $this->associationPayload($database))->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function databases(): array
    {
        return $this->driver->databases();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tables(string $database): array
    {
        return $this->driver->tables($database);
    }

    /**
     * @return array<string, mixed>
     */
    public function table(string $database, string $table): array
    {
        return $this->driver->table($database, $table);
    }

    /**
     * @return array<string, mixed>
     */
    public function rows(string $database, string $table, int $page, int $perPage): array
    {
        return $this->driver->rows($database, $table, $page, $perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function query(User $user, string $database, string $sql, int $limit, string $currentPassword): array
    {
        $this->confirmOwnerPassword($user, $currentPassword);

        return $this->driver->execute($database, $sql, $limit);
    }

    public function confirmOwnerPassword(User $user, string $currentPassword): void
    {
        if (! $user->isOwner()) {
            throw new OperationBlockedException('Only owners may use the database workbench.');
        }

        if (! Hash::check($currentPassword, $user->password)) {
            throw new OperationBlockedException('The current password is invalid.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function associationPayload(WebsiteDatabase $database): array
    {
        return [
            'id' => $database->id,
            'website_id' => $database->website_id,
            'website_name' => $database->website?->name,
            'website_domain' => $database->website?->domain,
            'driver' => $database->driver,
            'host' => $database->host,
            'port' => $database->port,
            'database_name' => $database->database_name,
            'status' => $database->status,
            'source_relative_path' => $database->metadata['source_relative_path'] ?? null,
        ];
    }
}
