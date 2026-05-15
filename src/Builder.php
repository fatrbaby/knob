<?php

namespace Knob;

use Closure;
use Knob\Grammars\Grammar;
use PDO;

class Builder
{
    private PDO $connection;
    private ?string $table = null;
    private ?string $alias = null;

    private array $columns = ['*'];
    private array $joins = [];
    private array $wheres = [];
    private array $groups = [];
    private array $havings = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $unions = [];

    private array $insertValues = [];
    private array $updateValues = [];

    private Grammar $grammar;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->grammar = Knob::getDriver()->grammar();
    }

    public static function table(PDO $connection, string $table, ?string $alias = null): self
    {
        $builder = new self($connection);
        $builder->table = $table;
        $builder->alias = $alias;
        return $builder;
    }

    public function select(string|array ...$columns): Builder
    {
        $columns = is_array($columns[0] ?? null) ? $columns[0] : $columns;
        $this->columns = $columns;
        return $this;
    }

    public function selectSub(string $column, ?string $alias = null): Builder
    {
        $this->columns[] = ['column' => "({$column})", 'alias' => $alias];
        return $this;
    }

    public function selectRaw(string $expression): Builder
    {
        $this->columns[] = $expression;
        return $this;
    }

    public function from(string $table, ?string $alias = null): Builder
    {
        $this->table = $table;
        $this->alias = $alias;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): Builder
    {
        return $this->joinInternal($table, $first, $operator, $second, 'INNER JOIN');
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): Builder
    {
        return $this->joinInternal($table, $first, $operator, $second, 'LEFT JOIN');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): Builder
    {
        return $this->joinInternal($table, $first, $operator, $second, 'RIGHT JOIN');
    }

    public function crossJoin(string $table): Builder
    {
        return $this->joinInternal($table, '', '', '', 'CROSS JOIN');
    }

    private function joinInternal(string $table, string $first, string $operator, string $second, string $type): Builder
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'clauses' => $first ? [[$first, $operator, $second]] : [],
        ];
        return $this;
    }

    public function joinSub(Closure $callback, string $as, string $first, string $operator, string $second): Builder
    {
        $subBuilder = new self($this->connection);
        $callback($subBuilder);
        $sql = $subBuilder->toSql()['sql'];

        $this->joins[] = [
            'type' => 'INNER JOIN',
            'table' => "({$sql}) AS {$this->grammar->quoteIdentifier($as)}",
            'clauses' => $first ? [[$first, $operator, $second]] : [],
        ];
        return $this;
    }

    public function fromSub(Closure $callback, string $as): Builder
    {
        $subBuilder = new self($this->connection);
        $callback($subBuilder);
        $sql = $subBuilder->toSql()['sql'];

        $this->table = "({$sql})";
        $this->alias = $as;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value = null): Builder
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value = null): Builder
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];
        return $this;
    }

    public function whereIn(string $column, array $values): Builder
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function orWhereIn(string $column, array $values): Builder
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'OR',
        ];
        return $this;
    }

    public function whereNotIn(string $column, array $values): Builder
    {
        $this->wheres[] = [
            'type' => 'notIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereBetween(string $column, array $values): Builder
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
            'not' => false,
        ];
        return $this;
    }

    public function whereNotBetween(string $column, array $values): Builder
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
            'not' => true,
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

    public function whereSub(string $column, string $operator, Closure $callback): Builder
    {
        $subBuilder = new self($this->connection);
        $callback($subBuilder);

        $this->wheres[] = [
            'type' => 'sub',
            'column' => $column,
            'operator' => $operator,
            'query' => $subBuilder->toSql(),
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereExists(Closure $callback): Builder
    {
        $subBuilder = new self($this->connection);
        $callback($subBuilder);

        $this->wheres[] = [
            'type' => 'exists',
            'query' => $subBuilder->toSql(),
            'boolean' => 'AND',
            'not' => false,
        ];
        return $this;
    }

    public function whereNotExists(Closure $callback): Builder
    {
        $subBuilder = new self($this->connection);
        $callback($subBuilder);

        $this->wheres[] = [
            'type' => 'exists',
            'query' => $subBuilder->toSql(),
            'boolean' => 'AND',
            'not' => true,
        ];
        return $this;
    }

    public function groupBy(string|array ...$groups): Builder
    {
        $groups = is_array($groups[0] ?? null) ? $groups[0] : $groups;
        $this->groups = array_merge($this->groups, $groups);
        return $this;
    }

    public function having(string $column, string $operator, mixed $value = null): Builder
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->havings[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];
        return $this;
    }

    public function havingRaw(string $sql): Builder
    {
        $this->havings[] = [
            'type' => 'raw',
            'sql' => $sql,
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

    public function union(Closure $callback, bool $all = false): Builder
    {
        $subBuilder = new self($this->connection);
        $callback($subBuilder);

        $this->unions[] = [
            'all' => $all,
            'sql' => $subBuilder->toSql()['sql'],
        ];
        return $this;
    }

    public function unionAll(Closure $callback): Builder
    {
        return $this->union($callback, true);
    }

    public function insert(array $values): bool
    {
        $this->insertValues = $values;

        if (empty($this->table)) {
            throw new \RuntimeException('Table not set for insert');
        }

        $columns = array_keys(is_array($values[0] ?? null) ? $values[0] : $values);
        $components = [
            'table' => $this->table,
            'columns' => $columns,
            'values' => array_map(fn ($v) => array_values($v), array_values($values)),
        ];

        $sql = $this->grammar->compileInsert($components);
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function insertGetId(array $values, ?string $sequence = null): string|false
    {
        $this->insert($values);
        return $this->connection->lastInsertId($sequence);
    }

    public function update(array $values): int
    {
        $this->updateValues = $values;

        $components = [
            'table' => $this->table,
            'values' => $values,
            'wheres' => $this->wheres,
        ];

        $sql = $this->grammar->compileUpdate($components);
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $components = [
            'table' => $this->table,
            'wheres' => $this->wheres,
        ];

        $sql = $this->grammar->compileDelete($components);
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public function truncate(): bool
    {
        if (empty($this->table)) {
            return false;
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
        $this->columns = ["{$function}({$column})"];

        $sql = $this->grammar->compileSelect($this->getComponents());
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

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

    public function first(): ?array
    {
        $this->limit = 1;

        $sql = $this->grammar->compileSelect($this->getComponents());
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function pluck(string $column, ?string $key = null): Collection
    {
        $this->columns = $key ? [$column, $key] : [$column];

        $sql = $this->grammar->compileSelect($this->getComponents());
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($key) {
            return new Collection(array_column($results, $column, $key));
        }

        return new Collection(array_column($results, $column));
    }

    public function exists(): bool
    {
        $this->columns = [1];

        $sql = $this->grammar->compileSelect($this->getComponents());
        $bindings = $this->grammar->getBindings();
        $this->grammar->resetBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchColumn() > 0;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();

        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;

        $items = $this->get()->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    private function getComponents(): array
    {
        return [
            'columns' => $this->columns,
            'from' => [$this->table, $this->alias],
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

    public function toSql(): array
    {
        $components = $this->getComponents();
        return [
            ...$components,
            'sql' => $this->grammar->compileSelect($components),
            'bindings' => $this->grammar->getBindings(),
        ];
    }

    public function clone(): self
    {
        $builder = new self($this->connection);
        $builder->table = $this->table;
        $builder->alias = $this->alias;
        $builder->columns = $this->columns;
        $builder->joins = $this->joins;
        $builder->wheres = $this->wheres;
        $builder->groups = $this->groups;
        $builder->havings = $this->havings;
        $builder->orders = $this->orders;
        $builder->limit = $this->limit;
        $builder->offset = $this->offset;
        $builder->unions = $this->unions;
        $builder->grammar = $this->grammar;
        return $builder;
    }
}
