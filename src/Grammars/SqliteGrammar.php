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
}
