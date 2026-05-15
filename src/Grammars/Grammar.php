<?php

namespace Knob\Grammars;

use BackedEnum;

abstract class Grammar
{
    protected array $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'groupBy' => [],
        'having' => [],
        'order' => [],
        'union' => [],
        'insert' => [],
        'update' => [],
    ];

    abstract public function quoteIdentifier(string $identifier): string;

    public function compileSelect(array $components): string
    {
        $sql = [];

        if (! empty($components['columns'])) {
            $sql[] = 'SELECT ' . $this->compileColumns($components['columns']);
        }

        if (! empty($components['from'])) {
            $sql[] = 'FROM ' . $this->compileFrom($components['from']);
        }

        if (! empty($components['joins'])) {
            $sql[] = $this->compileJoins($components['joins']);
        }

        if (! empty($components['wheres'])) {
            $sql[] = 'WHERE ' . $this->compileWheres($components['wheres']);
        }

        if (! empty($components['groups'])) {
            $sql[] = 'GROUP BY ' . $this->compileGroups($components['groups']);
        }

        if (! empty($components['havings'])) {
            $sql[] = 'HAVING ' . $this->compileHavings($components['havings']);
        }

        if (! empty($components['orders'])) {
            $sql[] = $this->compileOrders($components['orders']);
        }

        if (! empty($components['limit'])) {
            $sql[] = $this->compileLimit($components['limit']);
        }

        if (! empty($components['offset'])) {
            $sql[] = $this->compileOffset($components['offset']);
        }

        if (! empty($components['unions'])) {
            $sql[] = $this->compileUnions($components['unions']);
        }

        return implode(' ', $sql);
    }

    protected function compileColumns(array $columns): string
    {
        return implode(', ', array_map(function ($column) {
            if (is_array($column)) {
                return $column['column'] . ' AS ' . $this->quoteIdentifier($column['alias']);
            }
            if ($column === '*') {
                return $column;
            }
            if (str_contains($column, '(') || str_contains($column, ')')) {
                return $column;
            }
            return $this->isQualified($column) ? $column : $this->quoteIdentifier($column);
        }, $columns));
    }

    protected function compileFrom(array $from): string
    {
        [$table, $alias] = $from;
        if (! $table) {
            return '';
        }
        if ($alias) {
            return $this->quoteIdentifier($table) . ' AS ' . $this->quoteIdentifier($alias);
        }
        return $this->quoteIdentifier($table);
    }

    protected function compileJoins(array $joins): string
    {
        $sql = [];
        foreach ($joins as $join) {
            $type = $join['type'];
            $table = $join['table'];
            $clauses = $join['clauses'];

            $sql[] = "{$type} JOIN {$this->quoteIdentifier($table)} ON " . $this->compileJoinClauses($clauses);
        }
        return implode(' ', $sql);
    }

    protected function compileJoinClauses(array $clauses): string
    {
        return implode(' AND ', array_map(function ($clause) {
            [$first, $operator, $second] = $clause;
            return "{$first} {$operator} {$second}";
        }, $clauses));
    }

    protected function compileWheres(array $wheres): string
    {
        $conditions = [];
        foreach ($wheres as $where) {
            $sql = $this->compileWhere($where);
            $boolean = $where['boolean'] ?? 'AND';
            $conditions[] = $boolean === 'OR' ? "({$sql})" : $sql;
        }
        return implode(' AND ', $conditions);
    }

    protected function compileWhere(array $where): string
    {
        $type = $where['type'];

        return match ($type) {
            'basic' => $this->compileWhereBasic($where),
            'in' => $this->compileWhereIn($where),
            'notIn' => $this->compileWhereNotIn($where),
            'between' => $this->compileWhereBetween($where),
            'null' => $this->compileWhereNull($where),
            'notNull' => $this->compileWhereNotNull($where),
            'sub' => $this->compileWhereSub($where),
            'exists' => $this->compileWhereExists($where),
            'group' => $this->compileWhereGroup($where),
            'raw' => $this->compileWhereRaw($where),
            default => throw new \RuntimeException("Unknown where type: {$type}"),
        };
    }

    protected function compileWhereBasic(array $where): string
    {
        $column = $where['column'];
        $operator = $where['operator'];
        $value = $where['value'];
        $boolean = $where['boolean'] ?? 'AND';

        $sql = "{$column} {$operator} ?";
        $this->addBinding($value, 'where');

        return $sql;
    }

    protected function compileWhereIn(array $where): string
    {
        $column = $where['column'];
        $values = $where['values'];

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->addBinding($values, 'where');

        return "{$column} IN ({$placeholders})";
    }

    protected function compileWhereNotIn(array $where): string
    {
        $column = $where['column'];
        $values = $where['values'];

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->addBinding($values, 'where');

        return "{$column} NOT IN ({$placeholders})";
    }

    protected function compileWhereBetween(array $where): string
    {
        $column = $where['column'];
        $values = $where['values'];
        $not = $where['not'] ?? false;

        $this->addBinding($values[0], 'where');
        $this->addBinding($values[1], 'where');

        $op = $not ? 'NOT BETWEEN' : 'BETWEEN';
        return "{$column} {$op} ? AND ?";
    }

    protected function compileWhereNull(array $where): string
    {
        $column = $where['column'];

        return "{$column} IS NULL";
    }

    protected function compileWhereNotNull(array $where): string
    {
        $column = $where['column'];

        return "{$column} IS NOT NULL";
    }

    protected function compileWhereSub(array $where): string
    {
        $column = $where['column'];
        $operator = $where['operator'];
        $query = $where['query'];
        $boolean = $where['boolean'] ?? 'AND';

        $sql = "{$column} {$operator} ({$query['sql']})";
        foreach ($query['bindings'] as $type => $values) {
            foreach ($values as $value) {
                $this->addBinding($value, 'where');
            }
        }

        return $sql;
    }

    protected function compileWhereExists(array $where): string
    {
        $query = $where['query'];
        $not = $where['not'] ?? false;

        $op = $not ? 'NOT EXISTS' : 'EXISTS';
        $sql = "{$op} ({$query['sql']})";
        foreach ($query['bindings'] as $type => $values) {
            foreach ($values as $value) {
                $this->addBinding($value, 'where');
            }
        }

        return $sql;
    }

    protected function compileWhereGroup(array $where): string
    {
        $wheres = $where['wheres'];
        if (empty($wheres)) {
            return '';
        }

        $parts = [];
        foreach ($wheres as $i => $w) {
            $sql = $this->compileWhere($w);
            if ($i === 0) {
                $parts[] = $sql;
            } else {
                $boolean = $w['boolean'] ?? 'AND';
                $parts[] = "{$boolean} {$sql}";
            }
        }

        return '(' . implode(' ', $parts) . ')';
    }

    protected function compileWhereRaw(array $where): string
    {
        $sql = $where['sql'];

        foreach ($where['bindings'] ?? [] as $binding) {
            $this->addBinding($binding, 'where');
        }

        return $sql;
    }

    protected function compileGroups(array $groups): string
    {
        return implode(', ', array_map(
            fn ($group) => $this->isQualified($group) ? $group : $this->quoteIdentifier($group),
            $groups
        ));
    }

    protected function compileHavings(array $havings): string
    {
        $conditions = [];
        foreach ($havings as $having) {
            $conditions[] = $this->compileHaving($having);
        }
        return implode(' AND ', $conditions);
    }

    protected function compileHaving(array $having): string
    {
        $type = $having['type'] ?? 'basic';

        return match ($type) {
            'basic' => $this->compileHavingBasic($having),
            'raw' => $having['sql'],
            default => throw new \RuntimeException("Unknown having type: {$type}"),
        };
    }

    protected function compileHavingBasic(array $having): string
    {
        $column = $having['column'];
        $operator = $having['operator'];
        $value = $having['value'];

        $this->addBinding($value, 'having');
        return "{$column} {$operator} ?";
    }

    protected function compileOrders(array $orders): string
    {
        return 'ORDER BY ' . implode(', ', array_map(
            fn ($order) => $order['column'] . ' ' . $order['direction'],
            $orders
        ));
    }

    abstract protected function compileLimit(int $limit): string;

    abstract protected function compileOffset(int $offset): string;

    protected function compileUnions(array $unions): string
    {
        $sql = [];
        foreach ($unions as $union) {
            $sql[] = ($union['all'] ? 'UNION ALL ' : 'UNION ') . $union['sql'];
        }
        return implode(' ', $sql);
    }

    public function compileInsert(array $components): string
    {
        $table = $this->quoteIdentifier($components['table']);
        $columns = implode(', ', array_map(
            fn ($col) => $this->quoteIdentifier($col),
            $components['columns']
        ));
        $placeholders = implode(', ', array_fill(0, count($components['values']), '(' . implode(', ', array_fill(0, count($components['values'][0]), '?')) . ')'));

        $this->addBinding(array_merge(...$components['values']), 'insert');

        return "INSERT INTO {$table} ({$columns}) VALUES {$placeholders}";
    }

    public function compileUpdate(array $components): string
    {
        $table = $this->quoteIdentifier($components['table']);
        $sets = [];

        foreach ($components['values'] as $column => $value) {
            $sets[] = $this->quoteIdentifier($column) . ' = ?';
            $this->addBinding($value, 'update');
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets);

        if (! empty($components['wheres'])) {
            $sql .= ' WHERE ' . $this->compileWheres($components['wheres']);
        }

        return $sql;
    }

    public function compileDelete(array $components): string
    {
        $table = $this->quoteIdentifier($components['table']);

        $sql = "DELETE FROM {$table}";

        if (! empty($components['wheres'])) {
            $sql .= ' WHERE ' . $this->compileWheres($components['wheres']);
        }

        return $sql;
    }

    public function compileTruncate(string $table): string
    {
        return 'TRUNCATE ' . $this->quoteIdentifier($table);
    }

    public function getBindings(): array
    {
        return array_merge(
            $this->bindings['select'],
            $this->bindings['from'],
            $this->bindings['join'],
            $this->bindings['where'],
            $this->bindings['groupBy'],
            $this->bindings['having'],
            $this->bindings['order'],
            $this->bindings['union'],
            $this->bindings['insert'],
            $this->bindings['update']
        );
    }

    public function resetBindings(): void
    {
        foreach ($this->bindings as $key => $value) {
            $this->bindings[$key] = [];
        }
    }

    protected function addBinding(mixed $value, string $type): void
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                $this->bindings[$type][] = $this->prepareValue($v);
            }
        } else {
            $this->bindings[$type][] = $this->prepareValue($value);
        }
    }

    protected function prepareValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        return $value;
    }

    protected function isQualified(string $column): bool
    {
        return str_contains($column, '.');
    }
}
