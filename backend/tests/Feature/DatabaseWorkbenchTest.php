<?php

use App\Exceptions\OperationBlockedException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Databases\DatabaseDriverInterface;
use App\Services\Databases\SqlStatementClassifier;

class FakeWorkbenchDriver implements DatabaseDriverInterface
{
    public function overview(): array
    {
        return ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'version' => '8.0', 'database_count' => 1, 'configured' => true];
    }

    public function databases(): array
    {
        return [['name' => 'app', 'system' => false]];
    }

    public function tables(string $database): array
    {
        return [['name' => 'users', 'type' => 'BASE TABLE', 'engine' => 'InnoDB', 'rows_estimate' => 1, 'size_bytes' => 1024]];
    }

    public function table(string $database, string $table): array
    {
        return ['database' => $database, 'name' => $table, 'columns' => [['name' => 'id', 'type' => 'bigint']], 'indexes' => []];
    }

    public function rows(string $database, string $table, int $page, int $perPage): array
    {
        return ['database' => $database, 'table' => $table, 'page' => $page, 'per_page' => $perPage, 'columns' => ['id'], 'rows' => [['id' => 1]]];
    }

    public function execute(string $database, string $sql, int $limit): array
    {
        $classification = app(SqlStatementClassifier::class)->classify($sql);
        if (! $classification['readonly']) {
            throw new OperationBlockedException($classification['reason'] ?? 'Only read-only SQL is enabled in this workbench.');
        }

        return ['database' => $database, 'classification' => $classification, 'sql' => $sql, 'columns' => ['id'], 'rows' => [['id' => 1]], 'row_count' => 1];
    }
}

it('classifies read only and blocked sql statements', function (): void {
    $classifier = app(SqlStatementClassifier::class);

    expect($classifier->classify('select * from users')['readonly'])->toBeTrue()
        ->and($classifier->classify('show tables')['readonly'])->toBeTrue()
        ->and($classifier->classify('delete from users')['readonly'])->toBeFalse()
        ->and($classifier->classify('select 1; select 2')['type'])->toBe('multi');
});

it('keeps database workbench owner only and requires password for queries', function (): void {
    app()->bind(DatabaseDriverInterface::class, FakeWorkbenchDriver::class);
    $owner = User::factory()->owner()->create();
    $viewer = User::factory()->viewer()->create();

    $this->actingAs($viewer)->getJson('/api/v1/databases')->assertForbidden();

    $this->actingAs($owner)
        ->getJson('/api/v1/databases')
        ->assertOk()
        ->assertJsonPath('data.databases.0.name', 'app');

    $this->actingAs($owner)
        ->postJson('/api/v1/databases/app/query', ['sql' => 'select * from users', 'current_password' => 'wrong'])
        ->assertStatus(422);

    $this->actingAs($owner)
        ->postJson('/api/v1/databases/app/query', ['sql' => 'select * from users', 'current_password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.result.row_count', 1);

    expect(AuditLog::query()->where('action', 'database.query.executed')->exists())->toBeTrue();
});
