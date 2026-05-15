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
}
