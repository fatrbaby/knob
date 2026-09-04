<?php

namespace Knob;

use Closure;
use Knob\Grammars\Grammar;
use PDO;

/**
 * @phpstan-type QueryPart array<array-key, mixed>
 * @phpstan-type SelectComponents array{distinct: bool, columns: array<array-key, string|int|float|QueryPart>, from: array{?string, ?string, list<mixed>}, joins: list<QueryPart>, wheres: list<QueryPart>, groups: array<array-key, string|QueryPart>, havings: list<QueryPart>, orders: list<QueryPart>, limit: ?int, offset: ?int, unions: list<QueryPart>}
 * @phpstan-type SelectSqlParts array{distinct: bool, columns: array<array-key, string|int|float|QueryPart>, from: array{?string, ?string, list<mixed>}, joins: list<QueryPart>, wheres: list<QueryPart>, groups: array<array-key, string|QueryPart>, havings: list<QueryPart>, orders: list<QueryPart>, limit: ?int, offset: ?int, unions: list<QueryPart>, sql: string, bindings: list<mixed>}
 * @phpstan-type Subquery array{sql: string, bindings: list<mixed>}
 * @phpstan-type InsertRow array<string, mixed>
 * @phpstan-type InsertComponents array{table: string, columns: list<string>, values: list<list<mixed>>, uniqueBy?: list<string>, update?: list<string>}
 */
class Builder
{
    private ?string $table = null;
    private ?string $alias = null;

    private bool $distinct = false;
    /** @var array<array-key, string|int|float|QueryPart> */
    private array $columns = ['*'];
    /** @var list<QueryPart> */
    private array $joins = [];
    /** @var list<mixed> */
    private array $fromBindings = [];
    /** @var list<QueryPart> */
    private array $wheres = [];
    /** @var array<array-key, string|QueryPart> */
    private array $groups = [];
    /** @var list<QueryPart> */
    private array $havings = [];
    /** @var list<QueryPart> */
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    /** @var list<QueryPart> */
    private array $unions = [];
    private bool $allowFullTable = false;

    private readonly Grammar $grammar;

    public function __construct(private readonly PDO $connection)
    {
        $this->grammar = Knob::getDriver()->grammar();
    }

    public static function table(PDO $connection, string $table, ?string $alias = null): self
    {
        $builder = new self($connection);
        $builder->table = $table;
        $builder->alias = $alias;

        return $builder;
    }

    /** @param string|list<string> ...$columns */
    public function select(string|array ...$columns): Builder
    {
        if (isset($columns[0]) && is_array($columns[0])) {
            $this->columns = $columns[0];
        } else {
            $this->columns = $columns;
        }

        return $this;
    }

    public function distinct(): Builder
    {
        $this->distinct = true;

        return $this;
    }

    public function allowFullTable(): Builder
    {
        $this->allowFullTable = true;

        return $this;
    }

    public function selectSub(string|Closure|Builder $column, ?string $alias = null): Builder
    {
        if (! is_string($column)) {
            $subQuery = $this->normalizeSubquery($column);
            $this->columns[] = [
                'column' => "({$subQuery['sql']})",
                'alias' => $alias,
                'bindings' => $subQuery['bindings'],
            ];

            return $this;
        }

        $this->columns[] = ['column' => "({$column})", 'alias' => $alias];

        return $this;
    }

    public function selectRaw(string $expression): Builder
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }

        $this->columns[] = [
            'type' => 'raw',
            'sql' => $expression,
        ];

        return $this;
    }

    public function from(string $table, ?string $alias = null): Builder
    {
        $this->table = $table;
        $this->alias = $alias;

        return $this;
    }

    public function join(string $table, string|Closure $first, ?string $operator = null, ?string $second = null, ?string $alias = null): Builder
    {
        return $this->joinInternal($table, $first, $operator, $second, 'INNER JOIN', $alias);
    }

    public function leftJoin(string $table, string|Closure $first, ?string $operator = null, ?string $second = null, ?string $alias = null): Builder
    {
        return $this->joinInternal($table, $first, $operator, $second, 'LEFT JOIN', $alias);
    }

    public function rightJoin(string $table, string|Closure $first, ?string $operator = null, ?string $second = null, ?string $alias = null): Builder
    {
        return $this->joinInternal($table, $first, $operator, $second, 'RIGHT JOIN', $alias);
    }

    public function crossJoin(string $table, ?string $alias = null): Builder
    {
        return $this->joinInternal($table, '', '', '', 'CROSS JOIN', $alias);
    }

    private function joinInternal(string $table, string|Closure $first, ?string $operator, ?string $second, string $type, ?string $alias = null): Builder
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'alias' => $alias,
            'clauses' => $this->normalizeJoinClauses($first, $operator, $second),
        ];

        return $this;
    }

    /** @return list<QueryPart> */
    private function normalizeJoinClauses(string|Closure $first, ?string $operator, ?string $second): array
    {
        if ($first instanceof Closure) {
            $join = new JoinClause();
            $first($join);

            return $join->getClauses();
        }

        if ($first === '') {
            return [];
        }

        if ($operator === null || $second === null) {
            throw new \RuntimeException('Join operator and second column are required for simple joins');
        }

        $operator = SqlOperator::normalize($operator);

        return [[
            'type' => 'on',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => 'AND',
        ]];
    }

    public function joinSub(Closure|Builder $callback, string $as, string|Closure $first, ?string $operator = null, ?string $second = null): Builder
    {
        return $this->joinSubInternal($callback, $as, $first, $operator, $second, 'INNER JOIN');
    }

    public function leftJoinSub(Closure|Builder $callback, string $as, string|Closure $first, ?string $operator = null, ?string $second = null): Builder
    {
        return $this->joinSubInternal($callback, $as, $first, $operator, $second, 'LEFT JOIN');
    }

    private function joinSubInternal(Closure|Builder $callback, string $as, string|Closure $first, ?string $operator, ?string $second, string $type): Builder
    {
        $clauses = is_string($first)
            ? $this->normalizeJoinClauses($first, $operator, $second)
            : null;
        $subQuery = $this->normalizeSubquery($callback);
        $sql = $subQuery['sql'];

        $clauses ??= $this->normalizeJoinClauses($first, $operator, $second);

        $this->joins[] = [
            'type' => $type,
            'table' => "({$sql}) AS {$this->grammar->quoteIdentifier($as)}",
            'clauses' => $clauses,
            'bindings' => $subQuery['bindings'],
        ];

        return $this;
    }

    public function fromSub(Closure|Builder $callback, string $as): Builder
    {
        $subQuery = $this->normalizeSubquery($callback);
        $sql = $subQuery['sql'];

        $this->table = "({$sql})";
        $this->alias = $as;
        $this->fromBindings = $subQuery['bindings'];

        return $this;
    }

    public function where(string|Closure $column, mixed $operator = null, mixed $value = null): Builder
    {
        if ($column instanceof Closure) {
            return $this->addWhereGroupClause($column, 'AND');
        }

        return $this->addWhereClause($column, $operator, $value, 'AND', func_num_args());
    }

    public function orWhere(string|Closure $column, mixed $operator = null, mixed $value = null): Builder
    {
        if ($column instanceof Closure) {
            return $this->addWhereGroupClause($column, 'OR');
        }

        return $this->addWhereClause($column, $operator, $value, 'OR', func_num_args());
    }

    /** @param array<mixed>|Closure|Builder $values */
    public function whereIn(string $column, array|Closure|Builder $values): Builder
    {
        if (! is_array($values)) {
            $this->wheres[] = [
                'type' => 'inSub',
                'column' => $column,
                'query' => $this->normalizeSubquery($values),
                'boolean' => 'AND',
            ];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
        ];

        return $this;
    }

    /** @param array<mixed>|Closure|Builder $values */
    public function orWhereIn(string $column, array|Closure|Builder $values): Builder
    {
        if (! is_array($values)) {
            $this->wheres[] = [
                'type' => 'inSub',
                'column' => $column,
                'query' => $this->normalizeSubquery($values),
                'boolean' => 'OR',
            ];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'OR',
        ];

        return $this;
    }

    /** @param array<mixed>|Closure|Builder $values */
    public function whereNotIn(string $column, array|Closure|Builder $values): Builder
    {
        if (! is_array($values)) {
            $this->wheres[] = [
                'type' => 'notInSub',
                'column' => $column,
                'query' => $this->normalizeSubquery($values),
                'boolean' => 'AND',
            ];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'notIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
        ];

        return $this;
    }

    /** @param array<mixed>|Closure|Builder $values */
    public function orWhereNotIn(string $column, array|Closure|Builder $values): Builder
    {
        if (! is_array($values)) {
            $this->wheres[] = [
                'type' => 'notInSub',
                'column' => $column,
                'query' => $this->normalizeSubquery($values),
                'boolean' => 'OR',
            ];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'notIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'OR',
        ];

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereBetween(string $column, array $values): Builder
    {
        return $this->addBetweenClause($column, $values, 'AND', false);
    }

    /** @param array<mixed> $values */
    public function orWhereBetween(string $column, array $values): Builder
    {
        return $this->addBetweenClause($column, $values, 'OR', false);
    }

    /** @param array<mixed> $values */
    public function whereNotBetween(string $column, array $values): Builder
    {
        return $this->addBetweenClause($column, $values, 'AND', true);
    }

    /** @param array<mixed> $values */
    public function orWhereNotBetween(string $column, array $values): Builder
    {
        return $this->addBetweenClause($column, $values, 'OR', true);
    }

    /** @param array<mixed> $values */
    private function addBetweenClause(string $column, array $values, string $boolean, bool $not): Builder
    {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('BETWEEN requires exactly two values');
        }

        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'values' => array_values($values),
            'boolean' => $boolean,
            'not' => $not,
        ];

        return $this;
    }

    public function whereLike(string $column, mixed $value): Builder
    {
        $this->wheres[] = [
            'type' => 'like',
            'column' => $column,
            'value' => $value,
            'boolean' => 'AND',
            'not' => false,
        ];

        return $this;
    }

    public function orWhereLike(string $column, mixed $value): Builder
    {
        $this->wheres[] = [
            'type' => 'like',
            'column' => $column,
            'value' => $value,
            'boolean' => 'OR',
            'not' => false,
        ];

        return $this;
    }

    public function whereNotLike(string $column, mixed $value): Builder
    {
        $this->wheres[] = [
            'type' => 'like',
            'column' => $column,
            'value' => $value,
            'boolean' => 'AND',
            'not' => true,
        ];

        return $this;
    }

    public function orWhereNotLike(string $column, mixed $value): Builder
    {
        $this->wheres[] = [
            'type' => 'like',
            'column' => $column,
            'value' => $value,
            'boolean' => 'OR',
            'not' => true,
        ];

        return $this;
    }

    public function whereColumn(string $first, string $operator, ?string $second = null): Builder
    {
        return $this->addWhereColumnClause($first, $operator, $second, 'AND');
    }

    public function orWhereColumn(string $first, string $operator, ?string $second = null): Builder
    {
        return $this->addWhereColumnClause($first, $operator, $second, 'OR');
    }

    private function addWhereColumnClause(string $first, string $operator, ?string $second, string $boolean): Builder
    {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        $operator = SqlOperator::normalize($operator);

        $this->wheres[] = [
            'type' => 'column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNull(string $column): Builder
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function orWhereNull(string $column): Builder
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'OR',
        ];

        return $this;
    }

    public function whereNotNull(string $column): Builder
    {
        $this->wheres[] = [
            'type' => 'notNull',
            'column' => $column,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function orWhereNotNull(string $column): Builder
    {
        $this->wheres[] = [
            'type' => 'notNull',
            'column' => $column,
            'boolean' => 'OR',
        ];

        return $this;
    }

    /** @param array<mixed> $bindings */
    public function whereRaw(string $sql, array $bindings = []): Builder
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'bindings' => $bindings,
            'boolean' => 'AND',
        ];

        return $this;
    }

    /** @param array<mixed> $bindings */
    public function orWhereRaw(string $sql, array $bindings = []): Builder
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'bindings' => $bindings,
            'boolean' => 'OR',
        ];

        return $this;
    }

    public function whereSub(string $column, string $operator, Closure|Builder $callback): Builder
    {
        return $this->addWhereSubClause($column, $operator, $callback, 'AND');
    }

    public function orWhereSub(string $column, string $operator, Closure|Builder $callback): Builder
    {
        return $this->addWhereSubClause($column, $operator, $callback, 'OR');
    }

    private function addWhereSubClause(string $column, string $operator, Closure|Builder $callback, string $boolean): Builder
    {
        $operator = SqlOperator::normalize($operator);

        $this->wheres[] = [
            'type' => 'sub',
            'column' => $column,
            'operator' => $operator,
            'query' => $this->normalizeSubquery($callback),
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNot(Closure $callback): Builder
    {
        return $this->addWhereNotClause($callback, 'AND');
    }

    public function orWhereNot(Closure $callback): Builder
    {
        return $this->addWhereNotClause($callback, 'OR');
    }

    public function whereExists(Closure|Builder $callback): Builder
    {
        return $this->addWhereExistsClause($callback, 'AND', false);
    }

    public function orWhereExists(Closure|Builder $callback): Builder
    {
        return $this->addWhereExistsClause($callback, 'OR', false);
    }

    public function whereNotExists(Closure|Builder $callback): Builder
    {
        return $this->addWhereExistsClause($callback, 'AND', true);
    }

    public function orWhereNotExists(Closure|Builder $callback): Builder
    {
        return $this->addWhereExistsClause($callback, 'OR', true);
    }

    private function addWhereExistsClause(Closure|Builder $callback, string $boolean, bool $not): Builder
    {
        $this->wheres[] = [
            'type' => 'exists',
            'query' => $this->normalizeSubquery($callback),
            'boolean' => $boolean,
            'not' => $not,
        ];

        return $this;
    }

    public function whereDate(string $column, mixed $operator, mixed $value = null): Builder
    {
        return $this->addDateWhereClause('date', $column, $operator, $value, 'AND', func_num_args());
    }

    public function orWhereDate(string $column, mixed $operator, mixed $value = null): Builder
    {
        return $this->addDateWhereClause('date', $column, $operator, $value, 'OR', func_num_args());
    }

    public function whereTime(string $column, mixed $operator, mixed $value = null): Builder
    {
        return $this->addDateWhereClause('time', $column, $operator, $value, 'AND', func_num_args());
    }

    public function orWhereTime(string $column, mixed $operator, mixed $value = null): Builder
    {
        return $this->addDateWhereClause('time', $column, $operator, $value, 'OR', func_num_args());
    }

    public function whereYear(string $column, mixed $operator, mixed $value = null): Builder
    {
        return $this->addDateWhereClause('year', $column, $operator, $value, 'AND', func_num_args());
    }

    public function orWhereYear(string $column, mixed $operator, mixed $value = null): Builder
    {
        return $this->addDateWhereClause('year', $column, $operator, $value, 'OR', func_num_args());
    }

    public function whereMonth(string $column, mixed $operator, mixed $value = null): Builder
    {
        return $this->addDateWhereClause('month', $column, $operator, $value, 'AND', func_num_args());
    }

    public function orWhereMonth(string $column, mixed $operator, mixed $value = null): Builder
    {
        return $this->addDateWhereClause('month', $column, $operator, $value, 'OR', func_num_args());
    }

    /** @param string|list<string> ...$groups */
    public function groupBy(string|array ...$groups): Builder
    {
        if (isset($groups[0]) && is_array($groups[0])) {
            $newGroups = $groups[0];
        } else {
            $newGroups = $groups;
        }

        $this->groups = array_merge($this->groups, $newGroups);

        return $this;
    }

    /** @param array<mixed> $bindings */
    public function groupByRaw(string $sql, array $bindings = []): Builder
    {
        $this->groups[] = [
            'type' => 'raw',
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $this;
    }

    public function having(string $column, string $operator, mixed $value = null): Builder
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $operator = SqlOperator::normalize($operator);

        $this->havings[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /** @param array<mixed> $bindings */
    public function havingRaw(string $sql, array $bindings = []): Builder
    {
        $this->havings[] = [
            'type' => 'raw',
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): Builder
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];

        return $this;
    }

    /** @param array<mixed> $bindings */
    public function orderByRaw(string $sql, array $bindings = []): Builder
    {
        $this->orders[] = [
            'type' => 'raw',
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $this;
    }

    public function orderByDesc(string $column): Builder
    {
        return $this->orderBy($column, 'DESC');
    }

    public function latest(string $column = 'created_at'): Builder
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): Builder
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $limit): Builder
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): Builder
    {
        $this->offset = $offset;

        return $this;
    }

    public function union(Closure|Builder $callback, bool $all = false): Builder
    {
        $subQuery = $this->normalizeUnionSubquery($callback);

        $this->unions[] = [
            'all' => $all,
            'sql' => $subQuery['sql'],
            'bindings' => $subQuery['bindings'],
            'requiresWrapper' => $subQuery['limit'] !== null
                || $subQuery['offset'] !== null
                || ! empty($subQuery['unions']),
        ];

        return $this;
    }

    public function unionAll(Closure|Builder $callback): Builder
    {
        return $this->union($callback, true);
    }

    /** @param InsertRow|list<InsertRow> $values */
    public function insert(array $values): bool
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Table not set for insert');
        }

        $components = $this->prepareInsertComponents($values, $this->table);

        $sql = $this->grammar->compileInsert($components);
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);

        return $stmt->execute($bindings);
    }

    /** @param InsertRow|list<InsertRow> $values */
    public function insertOrIgnore(array $values): int
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Table not set for insert');
        }

        $components = $this->prepareInsertComponents($values, $this->table);

        $sql = $this->grammar->compileInsertOrIgnore($components);
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /** @param InsertRow $values */
    public function insertGetId(array $values, ?string $sequence = null): string|false
    {
        $this->insert($values);

        return $this->connection->lastInsertId($sequence);
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Table not set for update');
        }

        if ($values === []) {
            throw new \InvalidArgumentException('Update values cannot be empty');
        }

        if ($this->wheres === [] && ! $this->allowFullTable) {
            throw new \RuntimeException('Full table update requires allowFullTable()');
        }

        $components = [
            'table' => $this->table,
            'values' => $values,
            'wheres' => $this->wheres,
        ];

        $sql = $this->grammar->compileUpdate($components);
        $bindings = $this->grammar->getUpdateBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * @param InsertRow|list<InsertRow> $values
     * @param string|array<string> $uniqueBy
     * @param array<string>|null $update
     */
    public function upsert(array $values, string|array $uniqueBy, ?array $update = null): int
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Table not set for insert');
        }

        $components = $this->prepareInsertComponents($values, $this->table);
        $uniqueBy = is_array($uniqueBy) ? array_values($uniqueBy) : [$uniqueBy];

        if ($uniqueBy === []) {
            throw new \RuntimeException('Upsert unique columns cannot be empty');
        }

        $components['uniqueBy'] = $uniqueBy;
        $components['update'] = $update === null
            ? array_values(array_diff($components['columns'], $uniqueBy))
            : array_values($update);

        $sql = $this->grammar->compileUpsert($components);
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Table not set for delete');
        }

        if ($this->wheres === [] && ! $this->allowFullTable) {
            throw new \RuntimeException('Full table delete requires allowFullTable()');
        }

        $components = [
            'table' => $this->table,
            'wheres' => $this->wheres,
        ];

        $sql = $this->grammar->compileDelete($components);
        $bindings = $this->grammar->getDeleteBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    public function truncate(): bool
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Table not set for truncate');
        }

        $sql = $this->grammar->compileTruncate($this->table);
        $this->connection->exec($sql);

        return true;
    }

    public function count(): int
    {
        return (int) $this->aggregate('COUNT', '*');
    }

    public function sum(string $column): int|float
    {
        return $this->aggregate('SUM', $column);
    }

    public function avg(string $column): int|float|null
    {
        return $this->aggregate('AVG', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    private function aggregate(string $function, string $column): mixed
    {
        $builder = $this->clone();
        $builder->columns = [[
            'type' => 'raw',
            'sql' => "{$function}({$builder->grammar->wrapIdentifier($column)})",
        ]];

        $sql = $builder->grammar->compileSelect($builder->getComponents());
        $bindings = $builder->grammar->getBindings();
        $builder->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchColumn();
    }

    public function get(): Collection
    {
        $sql = $this->grammar->compileSelect($this->getComponents());
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return new Collection($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function cursor(): \Generator
    {
        $sql = $this->grammar->compileSelect($this->getComponents());
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            yield $row;
        }
    }

    public function chunk(int $count, callable $callback): bool
    {
        if ($count < 1) {
            throw new \RuntimeException('Chunk size must be at least 1');
        }

        $page = 0;

        do {
            $items = $this->clone()
                ->limit($count)
                ->offset($page * $count)
                ->get();

            if ($items->count() === 0) {
                return true;
            }

            if ($callback($items, $page + 1) === false) {
                return false;
            }

            $page++;
        } while ($items->count() === $count);

        return true;
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $builder = $this->clone();
        $builder->limit = 1;

        $sql = $builder->grammar->compileSelect($builder->getComponents());
        $bindings = $builder->grammar->getBindings();
        $builder->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function value(string $column): mixed
    {
        $builder = $this->clone();
        $resultColumn = $this->resultColumnName($column);
        $rawColumn = $builder->selectedRawColumn($resultColumn);

        $builder->columns = [$rawColumn ?? $column];
        $builder->limit = 1;

        $sql = $builder->grammar->compileSelect($builder->getComponents());
        $bindings = $builder->grammar->getBindings();
        $builder->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row[$resultColumn];
    }

    public function pluck(string $column, ?string $key = null): Collection
    {
        $builder = $this->clone();
        $columnKey = $this->resultColumnName($column);
        $keyKey = $key === null ? null : $this->resultColumnName($key);
        $selectedColumn = $builder->selectedRawColumn($columnKey) ?? $column;
        $selectedKey = $keyKey === null ? null : ($builder->selectedRawColumn($keyKey) ?? $key);

        $builder->columns = $selectedKey === null ? [$selectedColumn] : [$selectedColumn, $selectedKey];

        $sql = $builder->grammar->compileSelect($builder->getComponents());
        $bindings = $builder->grammar->getBindings();
        $builder->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($keyKey !== null) {
            return new Collection(array_column($results, $columnKey, $keyKey));
        }

        return new Collection(array_column($results, $columnKey));
    }

    public function exists(): bool
    {
        $builder = $this->clone();
        $builder->columns = [1];
        $builder->limit = 1;

        $sql = $builder->grammar->compileSelect($builder->getComponents());
        $bindings = $builder->grammar->getBindings();
        $builder->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchColumn() > 0;
    }

    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /** @return array{items: array<array-key, mixed>, total: int, per_page: int, current_page: int, last_page: int} */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        if ($perPage < 1) {
            throw new \InvalidArgumentException('Items per page must be at least 1');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be at least 1');
        }

        $itemsBuilder = $this->clone();

        $total = $this->paginationTotal();
        $items = $itemsBuilder
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get()
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    private function paginationTotal(): int
    {
        $builder = $this->clone();
        $builder->orders = [];
        $builder->limit = null;
        $builder->offset = null;

        $sql = $builder->grammar->compileSelect($builder->getComponents());
        $bindings = $builder->grammar->getBindings();
        $builder->grammar->resetBindings();
        $alias = $builder->grammar->wrapIdentifier('__knob_aggregate');

        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM ({$sql}) AS {$alias}");
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn();
    }

    /** @return SelectComponents */
    private function getComponents(): array
    {
        return [
            'distinct' => $this->distinct,
            'columns' => $this->columns,
            'from' => [$this->table, $this->alias, $this->fromBindings],
            'joins' => $this->joins,
            'wheres' => $this->wheres,
            'groups' => $this->groups,
            'havings' => $this->havings,
            'orders' => $this->orders,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'unions' => $this->unions,
        ];
    }

    /** @return SelectSqlParts */
    public function toSqlParts(): array
    {
        $components = $this->getComponents();
        $this->grammar->resetBindings();
        $sql = $this->grammar->compileSelect($components);
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        return [
            ...$components,
            'sql' => $sql,
            'bindings' => $bindings,
        ];
    }

    public function toSql(): string
    {
        $query = $this->toSqlParts();

        return $this->interpolateBindings($query['sql'], $query['bindings']);
    }

    /** @param list<mixed> $bindings */
    private function interpolateBindings(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $value = $this->formatBindingValue($binding);
            $sql = preg_replace('/\?/', $value, $sql, 1) ?? $sql;
        }

        return $sql;
    }

    private function formatBindingValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $quoted = $this->connection->quote((string) $value);

        if ($quoted === false) {
            return "'" . str_replace("'", "''", (string) $value) . "'";
        }

        return $quoted;
    }

    /** @return Subquery */
    private function normalizeSubquery(Closure|Builder|string $query): array
    {
        if (is_string($query)) {
            return [
                'sql' => $query,
                'bindings' => [],
            ];
        }

        if ($query instanceof Closure) {
            $subBuilder = new self($this->connection);
            $query($subBuilder);

            return $subBuilder->toSqlParts();
        }

        return $query->clone()->toSqlParts();
    }

    /** @return SelectSqlParts */
    private function normalizeUnionSubquery(Closure|Builder $query): array
    {
        if ($query instanceof Closure) {
            $builder = new self($this->connection);
            $query($builder);
        } else {
            $builder = $query->clone();
        }

        if ($builder->orders !== [] && $builder->limit === null && $builder->offset === null) {
            $builder->orders = [];
        }

        return $builder->toSqlParts();
    }

    private function resultColumnName(string $column): string
    {
        $aliasParts = preg_split('/\s+as\s+/i', trim($column), 2) ?: [trim($column)];

        if (isset($aliasParts[1])) {
            return $aliasParts[1];
        }

        $qualifiedParts = explode('.', $aliasParts[0]);

        return $qualifiedParts[array_key_last($qualifiedParts)];
    }

    /** @return QueryPart|null */
    private function selectedRawColumn(string $column): ?array
    {
        foreach ($this->columns as $selected) {
            if (! is_array($selected) || ($selected['type'] ?? null) !== 'raw') {
                continue;
            }

            $parts = preg_split('/\s+as\s+/i', trim($selected['sql']), 2);

            if (($parts[1] ?? null) === $column) {
                return $selected;
            }
        }

        return null;
    }

    private function addWhereClause(string $column, mixed $operator, mixed $value, string $boolean, int $argumentCount): Builder
    {
        if ($argumentCount === 2) {
            $value = $operator;
            $operator = '=';
        }

        $operator = SqlOperator::normalize($operator);

        if ($value === null && in_array($operator, ['=', '!=', '<>'], true)) {
            $this->wheres[] = [
                'type' => $operator === '=' ? 'null' : 'notNull',
                'column' => $column,
                'boolean' => $boolean,
            ];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    private function addWhereNotClause(Closure $callback, string $boolean): Builder
    {
        $subBuilder = new self($this->connection);
        $callback($subBuilder);

        if ($subBuilder->wheres === []) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'not',
            'wheres' => $subBuilder->wheres,
            'boolean' => $boolean,
        ];

        return $this;
    }

    private function addWhereGroupClause(Closure $callback, string $boolean): Builder
    {
        $subBuilder = new self($this->connection);
        $callback($subBuilder);

        if ($subBuilder->wheres === []) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'group',
            'wheres' => $subBuilder->wheres,
            'boolean' => $boolean,
        ];

        return $this;
    }

    private function addDateWhereClause(string $type, string $column, mixed $operator, mixed $value, string $boolean, int $argumentCount): Builder
    {
        if ($argumentCount === 2) {
            $value = $operator;
            $operator = '=';
        }

        $operator = SqlOperator::normalize($operator);

        $this->wheres[] = [
            'type' => $type,
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * @param InsertRow|list<InsertRow> $values
     * @return InsertComponents
     */
    private function prepareInsertComponents(array $values, string $table): array
    {
        if ($values === []) {
            throw new \RuntimeException('Insert values cannot be empty');
        }

        $rows = is_array($values[0] ?? null) ? array_values($values) : [$values];
        $columns = array_keys($rows[0]);

        if ($columns === []) {
            throw new \RuntimeException('Insert values cannot be empty');
        }

        foreach ($columns as $column) {
            if (! is_string($column)) {
                throw new \InvalidArgumentException('Insert column names must be strings');
            }
        }

        foreach ($rows as $row) {
            if (array_diff($columns, array_keys($row)) !== [] || array_diff(array_keys($row), $columns) !== []) {
                throw new \RuntimeException('Insert rows must have the same columns');
            }
        }

        return [
            'table' => $table,
            'columns' => $columns,
            'values' => array_map(fn ($row) => array_map(fn ($column) => $row[$column], $columns), $rows),
        ];
    }

    public function clone(): self
    {
        $builder = new self($this->connection);

        $builder->table = $this->table;
        $builder->alias = $this->alias;
        $builder->distinct = $this->distinct;
        $builder->columns = $this->columns;
        $builder->joins = $this->joins;
        $builder->fromBindings = $this->fromBindings;
        $builder->wheres = $this->wheres;
        $builder->groups = $this->groups;
        $builder->havings = $this->havings;
        $builder->orders = $this->orders;
        $builder->limit = $this->limit;
        $builder->offset = $this->offset;
        $builder->unions = $this->unions;
        $builder->allowFullTable = $this->allowFullTable;

        return $builder;
    }
}
