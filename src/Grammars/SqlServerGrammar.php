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
}
