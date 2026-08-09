<?php

namespace App\Services\Databases;

class SqlStatementClassifier
{
    /**
     * @return array{type: string, readonly: bool, statement: string, reason: string|null}
     */
    public function classify(string $sql): array
    {
        $statement = $this->normalize($sql);
        $statement = trim($statement);

        if ($statement === '') {
            return ['type' => 'empty', 'readonly' => false, 'statement' => '', 'reason' => 'SQL is empty.'];
        }

        if (strlen($sql) > (int) config('youpanel.database_admin.max_query_bytes', 20000)) {
            return ['type' => 'too_large', 'readonly' => false, 'statement' => $statement, 'reason' => 'SQL is too large.'];
        }

        if (preg_match('/\/\*!/i', $sql) === 1) {
            return ['type' => 'version_comment', 'readonly' => false, 'statement' => $statement, 'reason' => 'MySQL executable comments are not allowed.'];
        }

        $withoutTrailing = rtrim($statement);
        $withoutTrailing = str_ends_with($withoutTrailing, ';') ? substr($withoutTrailing, 0, -1) : $withoutTrailing;
        if (str_contains($withoutTrailing, ';')) {
            return ['type' => 'multi', 'readonly' => false, 'statement' => $statement, 'reason' => 'Multiple SQL statements are not allowed.'];
        }

        $keyword = strtolower(strtok(ltrim($withoutTrailing), " \t\r\n(") ?: '');
        $dangerous = $this->dangerousReason($withoutTrailing);
        if ($dangerous !== null) {
            return ['type' => $keyword ?: 'unknown', 'readonly' => false, 'statement' => $withoutTrailing, 'reason' => $dangerous];
        }

        $readonly = in_array($keyword, ['select', 'show', 'describe', 'desc', 'explain'], true)
            || ($keyword === 'with' && $this->isSafeCteSelect($withoutTrailing));

        return [
            'type' => $keyword ?: 'unknown',
            'readonly' => $readonly,
            'statement' => $withoutTrailing,
            'reason' => $readonly ? null : 'Only read-only SQL is enabled in this workbench.',
        ];
    }

    private function normalize(string $sql): string
    {
        $sql = str_replace(["\0", "\u{feff}"], '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql) ?? $sql;
        $sql = preg_replace('/--[^\r\n]*/', ' ', $sql) ?? $sql;
        $sql = preg_replace('/#[^\r\n]*/', ' ', $sql) ?? $sql;
        $sql = preg_replace('/\s+/u', ' ', $sql) ?? $sql;

        return trim($sql);
    }

    private function dangerousReason(string $sql): ?string
    {
        $patterns = [
            '/\binto\s+(out|dump)file\b/i' => 'Writing query results to server files is not allowed.',
            '/\bload\s+data\s+(local\s+)?infile\b/i' => 'Loading data from files is not allowed.',
            '/\bload_file\s*\(/i' => 'Reading server files through SQL is not allowed.',
            '/\binstall\s+plugin\b/i' => 'Plugin installation is not allowed.',
            '/\buninstall\s+plugin\b/i' => 'Plugin removal is not allowed.',
            '/\b(install|uninstall)\s+component\b/i' => 'MySQL component administration is not allowed.',
            '/\bshutdown\b/i' => 'Server shutdown is not allowed.',
            '/\b(grant|revoke|create\s+user|alter\s+user|drop\s+user|set\s+password)\b/i' => 'User and grant administration is not allowed.',
            '/^\s*delimiter\b/i' => 'Client delimiter directives are not allowed.',
        ];

        foreach ($patterns as $pattern => $reason) {
            if (preg_match($pattern, $sql) === 1) {
                return $reason;
            }
        }

        if ((string) config('youpanel.database_admin.mode', 'readonly') === 'readonly'
            && preg_match('/\b(insert|update|delete|drop|alter|create|truncate|replace|rename|call|do|handler|lock|unlock|set)\b/i', $sql) === 1) {
            return 'Only read-only SQL is enabled in this workbench.';
        }

        return null;
    }

    private function isSafeCteSelect(string $sql): bool
    {
        if (preg_match('/\b(insert|update|delete|drop|alter|create|truncate|replace|merge|call|grant|revoke|load|set)\b/i', $sql) === 1) {
            return false;
        }

        return preg_match('/\bselect\b/i', $sql) === 1;
    }
}
