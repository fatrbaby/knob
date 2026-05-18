# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Knob is a database query builder library ported from Laravel. It provides a fluent interface for building SQL queries across multiple database drivers.

## Commands

```bash
# Run all tests
./vendor/bin/pest

# Run tests with coverage
./vendor/bin/pest --coverage

# Run a specific test file
./vendor/bin/pest tests/Unit/ExampleTest.php
```

## OpenSpec

- OpenSpec workflow doc: `openspec/README.md`
- Baseline behavior specs: `openspec/specs/`
- Proposed changes: `openspec/changes/`

## Architecture

- **Knob** (`src/Knob.php`): Static facade entry point. Use `Knob::using($pdo)` to set the connection and `Knob::table($name)` to create a query builder.
- **Builder** (`src/Builder.php`): Builds queries via fluent methods (`select()`, `from()`, etc.). Delegates SQL compilation to a Grammar.
- **Driver** (`src/Driver.php`): PHP enum supporting MySQL, PostgreSQL, SQLite, SQLServer.
- **Grammar** (`src/Grammars/Grammar.php`): Abstract base for database-specific grammar with four implementations:
  - `MySqlGrammar` — MySQL/MariaDB
  - `PostgresGrammar` — PostgreSQL
  - `SqliteGrammar` — SQLite
  - `SqlServerGrammar` — SQL Server
- **Collection** (`src/Collection.php`): Results container implementing IteratorAggregate, Countable, and ArrayAccess.

## Design Notes

- The grammar system is driver-based: `Knob::getDriver()` inspects the PDO connection's driver name and returns the corresponding Driver enum case.
- Grammar classes compile query components to SQL strings for their specific database engine. Subclasses must implement `quoteIdentifier()`, `compileLimit()`, and `compileOffset()`.
- The binding system uses typed buckets (`select`, `where`, `insert`, etc.) that are merged and reset on each query.

## Usage

```php
use Knob\Knob;

// Set PDO connection (auto-detects driver)
Knob::using($pdo);

// SELECT
$users = Knob::table('users')
    ->select('id', 'name', 'email')
    ->where('status', '=', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// INSERT
Knob::table('users')->insert([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
]);

// UPDATE
Knob::table('users')
    ->where('id', 1)
    ->update(['name' => 'Alice Updated']);

// DELETE
Knob::table('users')->where('id', 1)->delete();

// Aggregates
$count = Knob::table('users')->count();
$max = Knob::table('users')->max('id');

// Transactions
Knob::transaction(function () {
    Knob::table('users')->insert(['name' => 'Carol']);
    Knob::table('posts')->insert(['user_id' => 1, 'title' => 'Hello']);
});
```

## Builder Methods

### Query Building
- `select(...$columns)`, `selectSub($column, $alias)`, `selectRaw($expression)`
- `from($table, $alias)`, `fromSub($callback, $as)`
- `join()`, `leftJoin()`, `rightJoin()`, `crossJoin()`, `joinSub()`

### Where Clauses
- `where()`, `orWhere()` — basic comparisons
- `whereIn()`, `whereNotIn()` — IN/NOT IN
- `whereBetween()`, `whereNotBetween()` — BETWEEN
- `whereNull()`, `whereNotNull()` — IS NULL/IS NOT NULL
- `whereSub()`, `whereExists()`, `whereNotExists()` — subqueries

### Other Clauses
- `groupBy()`, `having()`, `havingRaw()`
- `orderBy()`, `orderByDesc()`, `latest()`, `oldest()`
- `limit()`, `offset()`
- `union()`, `unionAll()`

### Execution
- `get()` — returns Collection
- `first()` — returns single row or null
- `pluck($column, $key)` — returns Collection of values
- `exists()` — returns bool
- `count()`, `sum()`, `avg()`, `max()`, `min()` — aggregates
- `paginate($perPage, $page)` — returns paginated array
- `insert()`, `insertGetId()`, `update()`, `delete()`, `truncate()`

### Utilities
- `toSql()` — returns ['sql' => string, 'bindings' => array]
- `clone()` — deep copy of builder
