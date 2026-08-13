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
            $select = ($components['distinct'] ?? false) ? 'SELECT DISTINCT ' : 'SELECT ';
            $sql[] = $select . $this->compileColumns($components['columns']);
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

        if (($components['limit'] ?? null) !== null || ($components['offset'] ?? null) !== null) {
            if (empty($components['orders']) && $this->requiresOrderForLimitOffset()) {
                $sql[] = $this->compileDefaultOrderForLimitOffset();
            }

            $sql[] = $this->compileLimitOffset($components['limit'] ?? null, $components['offset'] ?? null);
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
                foreach ($column['bindings'] ?? [] as $binding) {
                    $this->addBinding($binding, 'select');
                }

                return $column['column'] . ' AS ' . $this->quoteIdentifier($column['alias']);
            }

            if ($column === '*') {
                return $column;
            }

            if (is_int($column) || is_float($column)) {
                return (string) $column;
            }

            if (str_contains($column, '(') || str_contains($column, ')')) {
                return $column;
            }

            return $this->isQualified($column) ? $column : $this->quoteIdentifier($column);
        }, $columns));
    }

    protected function compileFrom(array $from): string
    {
        [$table, $alias, $bindings] = array_pad($from, 3, []);

        if (! $table) {
            return '';
        }

        foreach ($bindings as $binding) {
            $this->addBinding($binding, 'from');
        }
        $tableSql = str_starts_with($table, '(') ? $table : $this->quoteIdentifier($table);

        if ($alias) {
            return $tableSql . ' AS ' . $this->quoteIdentifier($alias);
        }

        return $tableSql;
    }

    protected function compileJoins(array $joins): string
    {
        $sql = [];

        foreach ($joins as $join) {
            ['type' => $type, 'table' => $table, 'clauses' => $clauses] = $join;
            $tableSql = $this->compileJoinTable($join);

            foreach ($join['bindings'] ?? [] as $binding) {
                $this->addBinding($binding, 'join');
            }

            if ($type === 'CROSS JOIN') {
                $sql[] = "{$type} {$tableSql}";
                continue;
            }

            $sql[] = "{$type} {$tableSql} ON " . $this->compileJoinClauses($clauses);
        }

        return implode(' ', $sql);
    }

    protected function compileJoinTable(array $join): string
    {
        $table = $join['table'];
        $alias = $join['alias'] ?? null;
        $tableSql = str_starts_with($table, '(') ? $table : $this->quoteIdentifier($table);

        if ($alias) {
            return $tableSql . ' AS ' . $this->quoteIdentifier($alias);
        }

        return $tableSql;
    }

    protected function compileJoinClauses(array $clauses): string
    {
        $compiled = [];

        foreach ($clauses as $i => $clause) {
            $sql = $this->compileJoinClause($clause);

            if ($i === 0) {
                $compiled[] = $sql;
                continue;
            }

            $boolean = $clause['boolean'] ?? 'AND';
            $compiled[] = "{$boolean} {$sql}";
        }

        return implode(' ', $compiled);
    }

    protected function compileJoinClause(array $clause): string
    {
        if (array_is_list($clause)) {
            [$first, $operator, $second] = $clause;

            return "{$first} {$operator} {$second}";
        }

        $type = $clause['type'] ?? 'on';

        return match ($type) {
            'on' => $this->compileJoinOnClause($clause),
            'basic' => $this->compileJoinBasicClause($clause),
            'null' => $this->compileJoinNullClause($clause),
            default => throw new \RuntimeException("Unknown join clause type: {$type}"),
        };
    }

    protected function compileJoinOnClause(array $clause): string
    {
        ['first' => $first, 'operator' => $operator, 'second' => $second] = $clause;

        return "{$first} {$operator} {$second}";
    }

    protected function compileJoinBasicClause(array $clause): string
    {
        ['column' => $column, 'operator' => $operator, 'value' => $value] = $clause;

        $this->addBinding($value, 'join');

        return "{$column} {$operator} ?";
    }

    protected function compileJoinNullClause(array $clause): string
    {
        $column = $clause['column'];
        $not = $clause['not'] ?? false;

        return $column . ($not ? ' IS NOT NULL' : ' IS NULL');
    }

    protected function compileWheres(array $wheres): string
    {
        $conditions = [];

        foreach ($wheres as $i => $where) {
            $sql = $this->compileWhere($where);

            if ($i === 0) {
                $conditions[] = $sql;
                continue;
            }

            $boolean = $where['boolean'] ?? 'AND';
            $conditions[] = "{$boolean} {$sql}";
        }

        return implode(' ', $conditions);
    }

    protected function compileWhere(array $where): string
    {
        $type = $where['type'];

        return match ($type) {
            'basic' => $this->compileWhereBasic($where),
            'in' => $this->compileWhereIn($where),
            'inSub' => $this->compileWhereInSub($where),
            'notIn' => $this->compileWhereNotIn($where),
            'notInSub' => $this->compileWhereNotInSub($where),
            'between' => $this->compileWhereBetween($where),
            'like' => $this->compileWhereLike($where),
            'column' => $this->compileWhereColumn($where),
            'null' => $this->compileWhereNull($where),
            'notNull' => $this->compileWhereNotNull($where),
            'sub' => $this->compileWhereSub($where),
            'exists' => $this->compileWhereExists($where),
            'group' => $this->compileWhereGroup($where),
            'not' => $this->compileWhereNot($where),
            'date', 'time', 'year', 'month' => $this->compileWhereDateBased($where),
            'raw' => $this->compileWhereRaw($where),
            default => throw new \RuntimeException("Unknown where type: {$type}"),
        };
    }

    protected function compileWhereBasic(array $where): string
    {
        ['column' => $column, 'operator' => $operator, 'value' => $value] = $where;

        $sql = "{$column} {$operator} ?";
        $this->addBinding($value, 'where');

        return $sql;
    }

    protected function compileWhereIn(array $where): string
    {
        $column = $where['column'];
        $values = $where['values'];

        if (empty($values)) {
            return '0 = 1';
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->addBinding($values, 'where');

        return "{$column} IN ({$placeholders})";
    }

    protected function compileWhereNotIn(array $where): string
    {
        $column = $where['column'];
        $values = $where['values'];

        if (empty($values)) {
            return '1 = 1';
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->addBinding($values, 'where');

        return "{$column} NOT IN ({$placeholders})";
    }

    protected function compileWhereInSub(array $where): string
    {
        return $this->compileWhereSubqueryList($where, false);
    }

    protected function compileWhereNotInSub(array $where): string
    {
        return $this->compileWhereSubqueryList($where, true);
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

    protected function compileWhereLike(array $where): string
    {
        $column = $where['column'];
        $value = $where['value'];
        $not = $where['not'] ?? false;

        $this->addBinding($value, 'where');

        $op = $not ? 'NOT LIKE' : 'LIKE';

        return "{$column} {$op} ?";
    }

    protected function compileWhereColumn(array $where): string
    {
        ['first' => $first, 'operator' => $operator, 'second' => $second] = $where;

        return "{$first} {$operator} {$second}";
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

        $sql = "{$column} {$operator} ({$query['sql']})";
        $this->addSubqueryBindings($query['bindings'], 'where');

        return $sql;
    }

    protected function compileWhereExists(array $where): string
    {
        $query = $where['query'];
        $not = $where['not'] ?? false;

        $op = $not ? 'NOT EXISTS' : 'EXISTS';
        $sql = "{$op} ({$query['sql']})";
        $this->addSubqueryBindings($query['bindings'], 'where');

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

    protected function compileWhereNot(array $where): string
    {
        $group = $this->compileWhereGroup($where);

        return $group === '' ? '' : "NOT {$group}";
    }

    protected function compileWhereDateBased(array $where): string
    {
        ['type' => $type, 'column' => $column, 'operator' => $operator, 'value' => $value] = $where;

        $this->addBinding($value, 'where');

        return $this->compileDateExpression($type, $column) . " {$operator} ?";
    }

    protected function compileWhereRaw(array $where): string
    {
        $sql = $where['sql'];

        foreach ($where['bindings'] ?? [] as $binding) {
            $this->addBinding($binding, 'where');
        }

        return $sql;
    }

    protected function compileWhereSubqueryList(array $where, bool $not): string
    {
        $column = $where['column'];
        $query = $where['query'];
        $operator = $not ? 'NOT IN' : 'IN';

        $this->addSubqueryBindings($query['bindings'], 'where');

        return "{$column} {$operator} ({$query['sql']})";
    }

    protected function addSubqueryBindings(array $bindings, string $type): void
    {
        foreach ($bindings as $value) {
            $this->addBinding($value, $type);
        }
    }

    protected function compileGroups(array $groups): string
    {
        return implode(', ', array_map(function ($group) {
            if (is_array($group)) {
                foreach ($group['bindings'] ?? [] as $binding) {
                    $this->addBinding($binding, 'groupBy');
                }

                return $group['sql'];
            }

            return $this->isQualified($group) ? $group : $this->quoteIdentifier($group);
        }, $groups));
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
            'raw' => $this->compileHavingRaw($having),
            default => throw new \RuntimeException("Unknown having type: {$type}"),
        };
    }

    protected function compileHavingBasic(array $having): string
    {
        ['column' => $column, 'operator' => $operator, 'value' => $value] = $having;

        $this->addBinding($value, 'having');

        return "{$column} {$operator} ?";
    }

    protected function compileHavingRaw(array $having): string
    {
        foreach ($having['bindings'] ?? [] as $binding) {
            $this->addBinding($binding, 'having');
        }

        return $having['sql'];
    }

    protected function compileOrders(array $orders): string
    {
        return 'ORDER BY ' . implode(', ', array_map(function ($order) {
            if (($order['type'] ?? null) === 'raw') {
                foreach ($order['bindings'] ?? [] as $binding) {
                    $this->addBinding($binding, 'order');
                }

                return $order['sql'];
            }

            return $order['column'] . ' ' . $order['direction'];
        }, $orders));
    }

    protected function compileDateExpression(string $type, string $column): string
    {
        return match ($type) {
            'date' => "DATE({$column})",
            'time' => "TIME({$column})",
            'year' => "EXTRACT(YEAR FROM {$column})",
            'month' => "EXTRACT(MONTH FROM {$column})",
            default => throw new \RuntimeException("Unknown date where type: {$type}"),
        };
    }

    protected function requiresOrderForLimitOffset(): bool
    {
        return false;
    }

    protected function compileDefaultOrderForLimitOffset(): string
    {
        return '';
    }

    abstract protected function compileLimit(int $limit): string;

    abstract protected function compileOffset(int $offset): string;

    protected function compileLimitOffset(?int $limit, ?int $offset): string
    {
        return implode(' ', array_filter([
            $limit !== null ? $this->compileLimit($limit) : null,
            $offset !== null ? $this->compileOffset($offset) : null,
        ]));
    }

    protected function compileUnions(array $unions): string
    {
        $sql = [];

        foreach ($unions as $union) {
            foreach ($union['bindings'] ?? [] as $binding) {
                $this->addBinding($binding, 'union');
            }
            $sql[] = ($union['all'] ? 'UNION ALL ' : 'UNION ') . $union['sql'];
        }

        return implode(' ', $sql);
    }

    public function compileInsert(array $components): string
    {
        $table = $this->quoteIdentifier($components['table']);
        $columns = implode(', ', array_map(
            $this->quoteIdentifier(...),
            $components['columns']
        ));
        $placeholders = $this->compileInsertPlaceholders($components['values']);

        $this->addBinding(array_merge(...$components['values']), 'insert');

        return "INSERT INTO {$table} ({$columns}) VALUES {$placeholders}";
    }

    public function compileInsertOrIgnore(array $components): string
    {
        return $this->compileInsert($components) . ' ON CONFLICT DO NOTHING';
    }

    public function compileUpsert(array $components): string
    {
        if (empty($components['update'])) {
            return $this->compileInsertOrIgnore($components);
        }

        $sql = $this->compileInsert($components);
        $uniqueBy = implode(', ', array_map(
            $this->quoteIdentifier(...),
            $components['uniqueBy']
        ));
        $updates = implode(', ', array_map(
            fn ($column) => $this->quoteIdentifier($column) . ' = excluded.' . $this->quoteIdentifier($column),
            $components['update']
        ));

        return "{$sql} ON CONFLICT ({$uniqueBy}) DO UPDATE SET {$updates}";
    }

    protected function compileInsertPlaceholders(array $values): string
    {
        $rowPlaceholder = $this->compileInsertRowPlaceholder(count($values[0]));

        return implode(', ', array_fill(0, count($values), $rowPlaceholder));
    }

    protected function compileInsertRowPlaceholder(int $columnCount): string
    {
        return '(' . implode(', ', array_fill(0, $columnCount, '?')) . ')';
    }

    public function compileUpdate(array $components): string
    {
        $this->bindings['update'] = [];
        $this->bindings['where'] = [];

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
        $this->bindings['where'] = [];

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

    public function getUpdateBindings(): array
    {
        return array_merge(
            $this->bindings['update'],
            $this->bindings['where']
        );
    }

    public function getDeleteBindings(): array
    {
        return $this->bindings['where'];
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
