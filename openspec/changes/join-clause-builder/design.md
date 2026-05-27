## Context

Existing joins are stored as:

```php
[
    'type' => 'INNER JOIN',
    'table' => $table,
    'alias' => $alias,
    'clauses' => [[$first, $operator, $second]],
]
```

The grammar compiles every clause as a column comparison joined by `AND`. This structure already hints at multiple clauses, but it lacks:

- per-clause boolean operators
- value predicates and bindings
- null checks
- a public API for adding more than one clause

## Goals / Non-Goals

**Goals:**

- Support common multi-condition joins without raw SQL.
- Keep existing simple joins fully compatible.
- Keep join bindings in the existing `join` binding bucket so placeholder order remains select, from, join, where.
- Use a focused join-clause object instead of overloading `Builder` where semantics.

**Non-Goals:**

- Do not add nested join clause groups in this change.
- Do not add lateral joins, CTEs, or driver-specific join extensions.
- Do not add full join / outer join variants unless needed by a later change.
- Do not quote existing raw column strings differently from current join behavior.

## Decisions

### 1. Add a dedicated `JoinClause`

`JoinClause` should collect join predicates in a compact structure:

```php
[
    'type' => 'on',
    'first' => 'users.id',
    'operator' => '=',
    'second' => 'posts.user_id',
    'boolean' => 'AND',
]
```

Value predicates use:

```php
[
    'type' => 'basic',
    'column' => 'memberships.active',
    'operator' => '=',
    'value' => true,
    'boolean' => 'AND',
]
```

Null predicates use:

```php
[
    'type' => 'null',
    'column' => 'posts.deleted_at',
    'boolean' => 'AND',
    'not' => false,
]
```

This keeps join logic separate from the main query builder and avoids exposing the full query API inside joins.

### 2. Preserve simple signatures

Existing calls like this must continue to work:

```php
Knob::table('users')->join('posts', 'users.id', '=', 'posts.user_id')
```

Callback joins add a second form:

```php
Knob::table('users')->join('posts', fn ($join) => $join
    ->on('users.id', '=', 'posts.user_id')
    ->whereNull('posts.deleted_at')
)
```

The same callback shape should apply to `leftJoin()`, `rightJoin()`, and `joinSub()`.

### 3. Compile join clauses recursively enough for current needs

The grammar only needs a flat list of join clauses for this change. The first clause omits its boolean; later clauses prepend `AND` or `OR`, matching where compilation behavior.

## Risks / Trade-offs

- Callback joins introduce a new public object. Keep it small and documented by tests.
- Join value predicates add bindings before outer where bindings; tests must lock this order.
- Some users may expect full Laravel join APIs. This change intentionally covers the portable core first.
