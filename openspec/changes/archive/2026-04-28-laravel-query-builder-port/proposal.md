## Why

Laravel's query builder provides an excellent fluent interface for SQL generation, but it depends on the full Laravel framework ecosystem (Container, ServiceProvider, facades). We need a lightweight, standalone query builder that generates and executes complex SQL without any Laravel dependencies.

## What Changes

- **New `Builder` class**: Fluent SQL query builder with support for Tier 1 + Tier 2 features
- **Enhanced `Grammar` system**: Per-driver SQL compilation (PostgresGrammar, MySqlGrammar, SqliteGrammar, SqlServerGrammar)
- **New `Collection` class**: Lazy, chainable result set wrapper with map/filter/reduce operations
- **Extended `Knob` facade**: Static entry point for creating queries and managing connections
- **Driver enum extensions**: Better PDO driver name to Driver enum mapping

### SQL Feature Support

**Tier 1 (Core)**
- `select()` — column selection with aliases
- `from()` — table source with optional alias
- `where()` / `orWhere()` — AND/OR conditions with operators
- `whereIn()` / `whereNotIn()` — IN clause support
- `whereBetween()` — BETWEEN range conditions
- `whereNull()` / `whereNotNull()` — NULL checks
- `join()` / `leftJoin()` / `rightJoin()` — INNER, LEFT, RIGHT joins
- `orderBy()` / `orderByDesc()` — sorting
- `limit()` / `offset()` — pagination

**Tier 2 (Extended)**
- Subqueries in WHERE, FROM, JOIN
- `union()` / `unionAll()` — query unions
- `groupBy()` / `having()` — aggregation support
- `insert()` / `update()` / `delete()` — data modification
- `count()`, `sum()`, `avg()`, `max()`, `min()` — aggregates

## Capabilities

### New Capabilities

- `query-builder`: Core fluent query builder with state management and SQL compilation
- `lazy-collection`: Lazy-evaluated, chainable collection for result sets
- `grammar-system`: Per-database grammar system for SQL generation
- `query-executors`: Methods to execute queries and return results (get, first, paginate)

### Modified Capabilities

- `knob` (existing): Extend to support direct query execution and result streaming

## Impact

- **New files**: `src/Builder.php`, `src/Collection.php`, `src/Grammars/*Grammar.php`
- **Modified files**: `src/Knob.php`, `src/Driver.php`, `src/Grammars/Grammar.php`
- **Dependencies**: Pure PHP 8.2+ with PDO extension — no external dependencies
- **API**: Fluent interface similar to Laravel's query builder for familiarity
