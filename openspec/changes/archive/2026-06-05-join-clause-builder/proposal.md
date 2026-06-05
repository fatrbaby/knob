## Why

Knob currently supports basic joins, join aliases, subquery joins, and cross joins, but each non-cross join can only express one `ON first operator second` condition. Many common application queries need richer join predicates:

- Join with multiple column comparisons
- Join with OR conditions
- Join with a value predicate inside the join condition
- Join subqueries with the same richer condition model

Examples:

- `JOIN posts ON users.id = posts.user_id AND posts.deleted_at IS NULL`
- `LEFT JOIN memberships ON users.id = memberships.user_id AND memberships.active = ?`
- `JOIN contacts ON users.id = contacts.user_id OR users.email = contacts.email`

Today these are awkward because `whereRaw()` applies to the outer query, not the join `ON` clause, and the current join API has no place to store join-specific boolean conditions or bindings.

## What Changes

- Introduce a join clause builder object for callback-based joins.
- Allow `join()`, `leftJoin()`, and `rightJoin()` to accept a callback instead of `first/operator/second`.
- Add join clause methods for:
  - `on()`
  - `orOn()`
  - `where()`
  - `orWhere()`
  - `whereNull()`
  - `orWhereNull()`
  - `whereNotNull()`
  - `orWhereNotNull()`
- Preserve existing simple join signatures.
- Propagate join-clause value bindings into the existing `join` binding bucket.
- Extend `joinSub()` to support callback join clauses while keeping the current simple condition signature.

## Capabilities

### Modified Capabilities

- `query-builder`: expands joins from single ON comparisons to grouped join-clause construction with column and value predicates.

## Impact

- `src/Builder.php` — widen join method signatures and store structured join clauses.
- `src/JoinClause.php` — add a small join clause builder object.
- `src/Grammars/Grammar.php` — compile structured join clauses and join bindings.
- `tests/Unit/BuilderTest.php` — add focused join clause behavior tests.
- `openspec/specs/query-builder/spec.md` — update baseline after implementation.
