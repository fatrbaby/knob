# Knob

Knob is a simple, lightweight database query builder inspired by Laravel. It provides a fluent, chainable interface for building SQL queries across multiple database drivers.

## Requirements

- PHP 8.3–8.x
- PDO extension and the driver required by your database

## Supported Databases

| Database | PDO driver | Integration coverage |
| --- | --- | --- |
| MySQL / MariaDB | `pdo_mysql` | CI service database |
| PostgreSQL | `pdo_pgsql` | CI service database |
| SQLite | `pdo_sqlite` | Default local and CI test suite |
| SQL Server | `pdo_sqlsrv` | CI service database |

## Installation

```bash
composer require fatrbaby/knob
```

## Quick Start

```php
use Knob\Knob;

// Set PDO connection (driver is auto-detected)
Knob::using($pdo);

// Build queries
$users = Knob::table('users')
    ->select('id', 'name', 'email')
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Insert
Knob::table('users')->insert([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
]);

// Update
Knob::table('users')
    ->where('id', 1)
    ->update(['name' => 'Updated Name']);

// Delete
Knob::table('users')->where('id', 1)->delete();

// Aggregates
$count = Knob::table('users')->count();
```

## Security

Knob binds values passed through structured query methods. SQL structure is a
separate trust boundary:

- Raw methods such as `selectRaw()`, `whereRaw()`, `orWhereRaw()`,
  `groupByRaw()`, `havingRaw()`, and `orderByRaw()` accept SQL fragments. Pass
  only trusted application code to them and, where a method accepts a bindings
  array, put data in that array. `selectRaw()` accepts no bindings, so its
  expression must contain no untrusted data. Never interpolate user input into
  a raw fragment.
- `selectSub()` also accepts a string overload. That string is inserted as a
  raw subquery and must therefore be trusted SQL without external data. Use a
  `Closure` or `Builder` subquery when values need bindings.
- Structured identifier arguments (for example table and column names) are
  quoted, but quoting is not authorization. Map user-facing sort, table, or
  column choices through an application-owned allowlist before calling Knob.
- Structured comparison methods accept only Knob's supported operator
  allowlist. If an operator comes from a request, map it to an allowed value;
  do not concatenate it into raw SQL.

## OpenSpec

This project uses OpenSpec for spec-driven changes.

- Workflow entry: `openspec/README.md`
- Baseline specs: `openspec/specs/`
- Active proposals: `openspec/changes/`

## Development

Install dependencies and run the same read-only quality gates used by CI:

```bash
composer install
composer ci
```

`composer ci` performs strict package validation, formatting checks, PHPStan
Level 8 analysis, the Pest suite, and a dependency security audit. The Makefile
delegates to the same commands:

```bash
make ci
make format # explicitly rewrites files with Pint
```

For local database smoke tests, copy the environment template and provide the
credentials for the databases you have available:

```bash
cp .env.example .env
```

Fill in the database credentials in `.env`, then run:

```bash
make smoke-mysql
make smoke-pgsql
make smoke-sqlsrv
```

Use `make smoke` to run all configured database smoke tests. Empty non-SQLite
DSNs are skipped locally; CI supplies every DSN and treats any missing one as a
failure.

## License

Licensed under the [Apache License 2.0](LICENSE).
