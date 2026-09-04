<?php

namespace Knob\Grammars;

/**
 * @phpstan-import-type InsertComponents from Grammar
 * @phpstan-import-type UpsertComponents from Grammar
 */
class MySqlGrammar extends Grammar
{
    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    protected function compileLimit(int $limit): string
    {
        return "LIMIT {$limit}";
    }

    protected function compileOffset(int $offset): string
    {
        return "OFFSET {$offset}";
    }

    protected function compileDateExpression(string $type, string $column): string
    {
        return match ($type) {
            'date' => "DATE({$column})",
            'time' => "TIME({$column})",
            'year' => "YEAR({$column})",
            'month' => "MONTH({$column})",
            default => parent::compileDateExpression($type, $column),
        };
    }

    /** @param InsertComponents $components */
    public function compileInsertOrIgnore(array $components): string
    {
        $sql = $this->compileInsert($components);

        return 'INSERT IGNORE' . substr($sql, strlen('INSERT'));
    }

    /** @param UpsertComponents $components */
    public function compileUpsert(array $components): string
    {
        if (empty($components['update'])) {
            return $this->compileInsertOrIgnore($components);
        }

        $sql = $this->compileInsert($components);
        $updates = implode(', ', array_map(
            fn ($column) => $this->wrapIdentifier($column) . ' = VALUES(' . $this->wrapIdentifier($column) . ')',
            $components['update']
        ));

        return "{$sql} ON DUPLICATE KEY UPDATE {$updates}";
    }
}
