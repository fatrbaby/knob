# Query Builder Spec

## Purpose

Define the current baseline behavior of Knob query building and SQL compilation.

## Capabilities

### 1. Fluent Query Building

- Build SELECT queries with `select`, `from`, joins, where clauses, groups, havings, order, limit/offset, and unions.
- Execute read queries via `get`, `first`, `pluck`, `exists`.
- Execute write queries via `insert`, `insertGetId`, `update`, `delete`, `truncate`.

### 2. Nested Where Groups

- `where(Closure)` and `orWhere(Closure)` create grouped predicates.
- Group internals support mixed AND/OR logic.
- Nested groups preserve binding order.

### 3. Cross-Driver Grammar

- Driver-specific identifier quoting for MySQL, PostgreSQL, SQLite, SQL Server.
- Driver-specific limit/offset compilation.

### 4. Subquery Support

- Subquery inputs are normalized from closures or reusable `Builder` instances where supported.
- `selectSub` supports raw SQL strings, closures, and reusable builders.
- `fromSub`, `joinSub`, `union`, and `unionAll` accept closures or reusable builders.
- `whereIn` and `whereNotIn` accept either value arrays or subquery inputs.
- `whereSub`, `whereExists`, and `whereNotExists` accept closures or reusable builders.
- Subquery bindings are propagated to parent query bindings in SQL placeholder order across select, from, join, where, and union components.

### 5. SQL Introspection

- `Builder::toSql()` returns SQL with bindings interpolated into placeholders.
- `Builder::toSqlParts()` returns structured query details:
  - SQL with placeholders
  - raw bindings
  - parsed components

## Constraints

- Values are parameterized during execution (`prepare` + bindings).
- Raw expressions are allowed and are the caller's responsibility.
- `whereIn([])` compiles to always-false condition.
- `whereNotIn([])` compiles to always-true condition.
