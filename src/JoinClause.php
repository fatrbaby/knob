<?php

namespace Knob;

class JoinClause
{
    private array $clauses = [];

    public function on(string $first, string $operator, string $second): self
    {
        return $this->addOnClause($first, $operator, $second, 'AND');
    }

    public function orOn(string $first, string $operator, string $second): self
    {
        return $this->addOnClause($first, $operator, $second, 'OR');
    }

    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        return $this->addBasicClause($column, $operator, $value, 'AND', func_num_args());
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        return $this->addBasicClause($column, $operator, $value, 'OR', func_num_args());
    }

    public function whereNull(string $column): self
    {
        return $this->addNullClause($column, 'AND', false);
    }

    public function orWhereNull(string $column): self
    {
        return $this->addNullClause($column, 'OR', false);
    }

    public function whereNotNull(string $column): self
    {
        return $this->addNullClause($column, 'AND', true);
    }

    public function orWhereNotNull(string $column): self
    {
        return $this->addNullClause($column, 'OR', true);
    }

    public function getClauses(): array
    {
        return $this->clauses;
    }

    private function addOnClause(string $first, string $operator, string $second, string $boolean): self
    {
        $this->clauses[] = [
            'type' => 'on',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
        ];

        return $this;
    }

    private function addBasicClause(string $column, mixed $operator, mixed $value, string $boolean, int $argumentCount): self
    {
        if ($argumentCount === 2) {
            $value = $operator;
            $operator = '=';
        }

        if ($value === null && in_array($operator, ['=', '!=', '<>'], true)) {
            return $this->addNullClause($column, $boolean, $operator !== '=');
        }

        $this->clauses[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    private function addNullClause(string $column, string $boolean, bool $not): self
    {
        $this->clauses[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => $not,
        ];

        return $this;
    }
}
