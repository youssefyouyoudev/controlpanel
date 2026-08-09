<?php

namespace App\Services\Databases;

use App\Exceptions\OperationBlockedException;
use PDO;
use PDOException;

class MySqlDatabaseDriver implements DatabaseDriverInterface
{
    public function __construct(private readonly SqlStatementClassifier $classifier) {}

    public function overview(): array
    {
        $pdo = $this->pdo();

        return [
            'driver' => 'mysql',
            'host' => config('youpanel.database_admin.host'),
            'port' => config('youpanel.database_admin.port'),
            'version' => $pdo->query('select version()')->fetchColumn() ?: null,
            'database_count' => count($this->databases()),
            'configured' => filled(config('youpanel.database_admin.username')),
            'mode' => config('youpanel.database_admin.mode', 'readonly'),
        ];
    }

    public function databases(): array
    {
        $rows = $this->pdo()->query('show databases')->fetchAll(PDO::FETCH_NUM);

        return array_values(array_map(fn (array $row): array => [
            'name' => (string) $row[0],
            'system' => in_array((string) $row[0], ['information_schema', 'mysql', 'performance_schema', 'sys'], true),
        ], $rows));
    }

    public function tables(string $database): array
    {
        $this->assertIdentifier($database);
        $statement = $this->pdo()->prepare(
            'select TABLE_NAME as name, TABLE_TYPE as type, ENGINE as engine, TABLE_ROWS as rows_estimate, (DATA_LENGTH + INDEX_LENGTH) as size_bytes, UPDATE_TIME as updated_at
             from information_schema.TABLES where TABLE_SCHEMA = ? order by TABLE_NAME'
        );
        $statement->execute([$database]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function table(string $database, string $table): array
    {
        $this->assertIdentifier($database);
        $this->assertIdentifier($table);

        $columns = $this->pdo()->prepare(
            'select COLUMN_NAME as name, COLUMN_TYPE as type, IS_NULLABLE as nullable, COLUMN_DEFAULT as default_value, COLUMN_KEY as column_key, EXTRA as extra
             from information_schema.COLUMNS where TABLE_SCHEMA = ? and TABLE_NAME = ? order by ORDINAL_POSITION'
        );
        $columns->execute([$database, $table]);

        $indexes = $this->pdo()->prepare(
            'select INDEX_NAME as name, COLUMN_NAME as column_name, NON_UNIQUE as non_unique, SEQ_IN_INDEX as sequence
             from information_schema.STATISTICS where TABLE_SCHEMA = ? and TABLE_NAME = ? order by INDEX_NAME, SEQ_IN_INDEX'
        );
        $indexes->execute([$database, $table]);

        return [
            'database' => $database,
            'name' => $table,
            'columns' => $columns->fetchAll(PDO::FETCH_ASSOC),
            'indexes' => $indexes->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function rows(string $database, string $table, int $page, int $perPage): array
    {
        $this->assertIdentifier($database);
        $this->assertIdentifier($table);
        $perPage = $this->boundedLimit($perPage);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $sql = sprintf('select * from %s.%s limit %d offset %d', $this->quoteIdentifier($database), $this->quoteIdentifier($table), $perPage, $offset);
        $rows = $this->pdo($database)->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $this->assertResponseSize($rows);

        return [
            'database' => $database,
            'table' => $table,
            'page' => $page,
            'per_page' => $perPage,
            'rows' => $rows,
            'columns' => array_keys($rows[0] ?? []),
        ];
    }

    public function execute(string $database, string $sql, int $limit): array
    {
        $this->assertIdentifier($database);
        if (strlen($sql) > (int) config('youpanel.database_admin.max_query_bytes', 20000)) {
            throw new OperationBlockedException('SQL is too large.');
        }

        $classification = $this->classifier->classify($sql);
        if (! $classification['readonly']) {
            throw new OperationBlockedException($classification['reason'] ?? 'Only read-only SQL is enabled in this workbench.');
        }

        $statementSql = $this->limitedSql($classification['statement'], $this->boundedLimit($limit));
        $statement = $this->pdo($database)->query($statementSql);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $this->assertResponseSize($rows);

        return [
            'database' => $database,
            'classification' => $classification,
            'sql' => $statementSql,
            'columns' => array_keys($rows[0] ?? []),
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    public function securityDiagnostics(): array
    {
        $diagnostics = [
            'mode' => config('youpanel.database_admin.mode', 'readonly'),
            'checked' => false,
            'dangerous_privileges' => [],
            'elevated_privileges' => [],
            'warnings' => [],
        ];

        try {
            $grants = array_map(
                fn (array $row): string => strtoupper(implode(' ', array_values($row))),
                $this->pdo()->query('show grants')->fetchAll(PDO::FETCH_ASSOC),
            );
        } catch (PDOException) {
            return [
                ...$diagnostics,
                'warnings' => ['Unable to inspect database grants. Review the workbench user manually.'],
            ];
        }

        $dangerousPatterns = [
            'ALL PRIVILEGES ON *.*',
            'FILE',
            'SUPER',
            'SYSTEM_USER',
            'SHUTDOWN',
            'RELOAD',
            'CREATE USER',
            'GRANT OPTION',
        ];
        $elevatedPatterns = ['PROCESS'];
        $dangerous = [];
        $elevated = [];

        foreach ($grants as $grant) {
            foreach ($dangerousPatterns as $privilege) {
                if (str_contains($grant, $privilege)) {
                    $dangerous[] = $privilege;
                }
            }

            foreach ($elevatedPatterns as $privilege) {
                if (str_contains($grant, $privilege)) {
                    $elevated[] = $privilege;
                }
            }
        }

        $dangerous = array_values(array_unique($dangerous));
        $elevated = array_values(array_unique($elevated));

        return [
            ...$diagnostics,
            'checked' => true,
            'dangerous_privileges' => $dangerous,
            'elevated_privileges' => $elevated,
            'warnings' => [
                ...($dangerous === [] ? [] : ['The database workbench user has dangerous server-level privileges.']),
                ...($elevated === [] ? [] : ['The database workbench user has elevated PROCESS visibility.']),
            ],
        ];
    }

    private function pdo(?string $database = null): PDO
    {
        if (! (bool) config('youpanel.database_admin.enabled')) {
            throw new OperationBlockedException('Database workbench is disabled.');
        }

        if (blank(config('youpanel.database_admin.username')) || blank(config('youpanel.database_admin.password'))) {
            throw new OperationBlockedException('Database workbench credentials are not configured.');
        }

        if (! in_array(config('youpanel.database_admin.mode'), ['readonly', 'managed'], true)) {
            throw new OperationBlockedException('Database workbench mode is invalid.');
        }

        $host = (string) config('youpanel.database_admin.host');
        $port = (int) config('youpanel.database_admin.port');
        $charset = (string) config('youpanel.database_admin.charset', 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        if ($database !== null) {
            $dsn .= ';dbname='.$database;
        }

        try {
            $pdo = new PDO($dsn, (string) config('youpanel.database_admin.username'), (string) config('youpanel.database_admin.password'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => (int) config('youpanel.database_admin.connection_timeout_seconds', 5),
            ]);
            $timeoutMs = max(1000, (int) config('youpanel.database_admin.query_timeout_seconds', 15) * 1000);
            try {
                $pdo->exec('set session max_execution_time='.$timeoutMs);
            } catch (PDOException) {
                // MariaDB and older MySQL versions may not support this variable.
            }

            return $pdo;
        } catch (PDOException $exception) {
            throw new OperationBlockedException('Unable to connect to the configured database server.', ['code' => $exception->getCode()]);
        }
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_$.-]+$/', $identifier) !== 1) {
            throw new OperationBlockedException('Database identifier is invalid.');
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        $this->assertIdentifier($identifier);

        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function boundedLimit(int $limit): int
    {
        return min(max(1, $limit), (int) config('youpanel.database_admin.max_row_limit', 500));
    }

    private function assertResponseSize(array $rows): void
    {
        $encoded = json_encode($rows);
        if ($encoded !== false && strlen($encoded) > (int) config('youpanel.database_admin.max_response_bytes', 1024 * 1024)) {
            throw new OperationBlockedException('Database response is too large.');
        }
    }

    private function limitedSql(string $sql, int $limit): string
    {
        if (preg_match('/^\s*(select|with)\b/i', $sql) === 1 && preg_match('/\blimit\s+\d+/i', $sql) !== 1) {
            return 'select * from ('.$sql.') as youpanel_query limit '.$limit;
        }

        return $sql;
    }
}
