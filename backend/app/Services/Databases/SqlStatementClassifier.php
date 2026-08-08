<?php

namespace App\Services\Databases;

class SqlStatementClassifier
{
    /**
     * @return array{type: string, readonly: bool, statement: string, reason: string|null}
     */
    public function classify(string $sql): array
    {
        $statement = trim(preg_replace('/--.*$/m', '', $sql) ?? $sql);
        $statement = trim($statement);

        if ($statement === '') {
            return ['type' => 'empty', 'readonly' => false, 'statement' => '', 'reason' => 'SQL is empty.'];
        }

        $withoutTrailing = rtrim($statement);
        $withoutTrailing = str_ends_with($withoutTrailing, ';') ? substr($withoutTrailing, 0, -1) : $withoutTrailing;
        if (str_contains($withoutTrailing, ';')) {
            return ['type' => 'multi', 'readonly' => false, 'statement' => $statement, 'reason' => 'Multiple SQL statements are not allowed.'];
        }

        $keyword = strtolower(strtok(ltrim($withoutTrailing), " \t\r\n(") ?: '');
        $readonly = in_array($keyword, ['select', 'show', 'describe', 'desc', 'explain', 'with'], true);

        return [
            'type' => $keyword ?: 'unknown',
            'readonly' => $readonly,
            'statement' => $withoutTrailing,
            'reason' => $readonly ? null : 'Only read-only SQL is enabled in this workbench.',
        ];
    }
}
