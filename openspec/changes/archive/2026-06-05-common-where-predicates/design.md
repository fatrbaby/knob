## Context

The current Builder can already model many complex queries through grouped where clauses and subqueries. The main gap is that several high-frequency predicates require `whereRaw()`, even though they are portable SQL and can be safely parameterized.

Existing predicate storage uses a flat array with a `type` discriminator and optional `boolean`. This is enough for the next set of predicates:

- LIKE and NOT LIKE are value-binding predicates.
- Column comparisons bind no values.
- OR variants can reuse existing predicate types with `boolean => 'OR'`.

## Goals / Non-Goals

**Goals:**

- Cover common application filters without requiring raw SQL.
- Keep API shape consistent with existing `where*` methods.
- Preserve placeholder and binding order.
- Keep behavior portable across MySQL, PostgreSQL, SQLite, and SQL Server.

**Non-Goals:**

- Do not introduce driver-specific date helpers in this change.
- Do not add join clause builders or multi-condition join callbacks.
- Do not add CTE, upsert, JSON, full-text search, or window-function APIs.
- Do not change identifier quoting behavior for existing raw column strings.

## Decisions

### 1. Use dedicated where types for LIKE and column comparisons

LIKE predicates should be represented as:

```php
[
    'type' => 'like',
    'column' => $column,
    'value' => $value,
    'boolean' => 'AND',
    'not' => false,
]
```

Column comparisons should be represented as:

```php
[
    'type' => 'column',
    'first' => $first,
    'operator' => $operator,
    'second' => $second,
    'boolean' => 'AND',
]
```

This avoids raw SQL for portable predicates and keeps compilation centralized in Grammar.

### 2. Reuse existing predicate types for OR variants

`orWhereNotIn()`, `orWhereBetween()`, and `orWhereNotBetween()` should use the same `notIn` / `notInSub` / `between` records already used by the AND versions, changing only `boolean`.

This keeps binding behavior identical and limits new Grammar code to genuinely new SQL shapes.

### 3. Keep column names as existing raw identifier strings

Existing where compilation does not quote predicate columns. This change should not alter that behavior. `whereColumn()` will compile `first operator second` directly, matching existing join condition conventions.

## Risks / Trade-offs

- LIKE behavior can vary by collation and case sensitivity across databases. This change only provides portable SQL shape, not case-insensitive matching guarantees.
- Column comparison operands remain caller-provided identifier strings, matching existing Builder conventions.
- Adding too many predicate helpers can bloat the API. This change limits scope to high-frequency, portable cases.
