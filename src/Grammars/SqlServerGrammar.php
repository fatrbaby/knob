<?php

namespace Knob\Grammars;

class SqlServerGrammar extends Grammar
{
    public function quoteIdentifier(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }

    protected function compileLimit(int $limit): string
    {
        return "OFFSET 0 ROWS FETCH NEXT {$limit} ROWS ONLY";
    }

    protected function compileOffset(int $offset): string
    {
        return "OFFSET {$offset} ROWS";
    }

    protected function compileLimitOffset(?int $limit, ?int $offset): string
    {
        $offset ??= 0;

        if ($limit === null) {
            return $this->compileOffset($offset);
        }

        return "OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY";
    }

    protected function requiresOrderForLimitOffset(): bool
    {
        return true;
    }

    protected function compileDefaultOrderForLimitOffset(): string
    {
        return 'ORDER BY (SELECT 0)';
    }

    protected function compileDateExpression(string $type, string $column): string
    {
        return match ($type) {
            'date' => "CAST({$column} AS date)",
            'time' => "CAST({$column} AS time)",
            'year' => "YEAR({$column})",
            'month' => "MONTH({$column})",
            default => parent::compileDateExpression($type, $column),
        };
    }

    public function compileInsertOrIgnore(array $components): string
    {
        throw new \RuntimeException('insertOrIgnore is not supported for SQL Server without conflict columns; use upsert instead');
    }

    public function compileUpsert(array $components): string
    {
        $table = $this->quoteIdentifier($components['table']);
        $columns = $components['columns'];
        $quotedColumns = array_map($this->quoteIdentifier(...), $columns);
        $rowPlaceholder = $this->compileInsertRowPlaceholder(count($columns));
        $placeholders = implode(', ', array_fill(0, count($components['values']), $rowPlaceholder));

        $this->addBinding(array_merge(...$components['values']), 'insert');

        $on = implode(' AND ', array_map(
            fn ($column) => 'target.' . $this->quoteIdentifier($column) . ' = source.' . $this->quoteIdentifier($column),
            $components['uniqueBy']
        ));
        $insertColumns = implode(', ', $quotedColumns);
        $insertValues = implode(', ', array_map(fn ($column) => 'source.' . $this->quoteIdentifier($column), $columns));

        $sql = "MERGE INTO {$table} AS target USING (VALUES {$placeholders}) AS source (" . implode(', ', $quotedColumns) . ") ON {$on}";

        if (! empty($components['update'])) {
            $updates = implode(', ', array_map(
                fn ($column) => 'target.' . $this->quoteIdentifier($column) . ' = source.' . $this->quoteIdentifier($column),
                $components['update']
            ));
            $sql .= " WHEN MATCHED THEN UPDATE SET {$updates}";
        }

        return "{$sql} WHEN NOT MATCHED THEN INSERT ({$insertColumns}) VALUES ({$insertValues});";
    }
}
