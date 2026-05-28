<?php

namespace Knob\Grammars;

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

    public function compileInsertOrIgnore(array $components): string
    {
        return preg_replace('/^INSERT INTO /', 'INSERT IGNORE INTO ', $this->compileInsert($components), 1);
    }

    public function compileUpsert(array $components): string
    {
        if (empty($components['update'])) {
            return $this->compileInsertOrIgnore($components);
        }

        $sql = $this->compileInsert($components);
        $updates = implode(', ', array_map(
            fn ($column) => $this->quoteIdentifier($column) . ' = VALUES(' . $this->quoteIdentifier($column) . ')',
            $components['update']
        ));

        return "{$sql} ON DUPLICATE KEY UPDATE {$updates}";
    }
}
