<?php

namespace Knob\Grammars;

class SqliteGrammar extends Grammar
{
    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
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
            'year' => "CAST(STRFTIME('%Y', {$column}) AS INTEGER)",
            'month' => "CAST(STRFTIME('%m', {$column}) AS INTEGER)",
            default => parent::compileDateExpression($type, $column),
        };
    }
}
