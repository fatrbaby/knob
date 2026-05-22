# Knob

Knob is a database query builder library ported from Laravel. It provides a fluent, chainable interface for building SQL queries across multiple database drivers.

## Requirements

- PHP 8.2+
- PDO extension

## Supported Databases

- MySQL / MariaDB
- PostgreSQL
- SQLite
- SQL Server

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

## OpenSpec

This project uses OpenSpec for spec-driven changes.

- Workflow entry: `openspec/README.md`
- Baseline specs: `openspec/specs/`
- Active proposals: `openspec/changes/`

## Testing

Run the default test suite:

```bash
make test
```

MySQL, PostgreSQL, and SQL Server smoke tests are optional and only run when their DSN environment variables are configured:

```bash
cp .env.example .env
```

Fill in the database credentials in `.env`, then run:

```bash
make smoke-mysql
make smoke-pgsql
make smoke-sqlsrv
```

Use `make smoke` to run all configured database smoke tests. Empty DSNs are skipped.

## License

Apache-2.0
