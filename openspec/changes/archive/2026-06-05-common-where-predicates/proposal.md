## Why

Knob already covers the main query shape: selects, joins, grouped where clauses, subqueries, aggregation, ordering, pagination, and writes. The remaining friction in common application queries is concentrated in predicate ergonomics. Today callers can express many missing cases with `whereRaw()`, but that pushes binding safety and SQL spelling back onto the caller.

Common examples include string matching, comparing two columns, and OR variants for existing predicates:

- `name LIKE ?`
- `users.created_at > users.updated_at`
- `status = ? OR age BETWEEN ? AND ?`
- `role NOT IN (?, ?) OR email IS NULL`

Adding these as first-class methods makes everyday filters easier to write without expanding into heavier features such as CTEs, upserts, join clause builders, or driver-specific date helpers.

## What Changes

- Add LIKE predicate helpers:
  - `whereLike()`
  - `orWhereLike()`
  - `whereNotLike()`
  - `orWhereNotLike()`
- Add column comparison helpers:
  - `whereColumn()`
  - `orWhereColumn()`
- Add missing OR variants for existing predicates:
  - `orWhereNotIn()`
  - `orWhereBetween()`
  - `orWhereNotBetween()`
- Reuse existing where storage and binding ordering rules.
- Add behavior tests for SQL compilation and bindings.

## Capabilities

### Modified Capabilities

- `query-builder`: expands first-class where predicates for common filtering scenarios while preserving existing binding behavior.

## Impact

- `src/Builder.php` — add public predicate helpers and internal where records.
- `src/Grammars/Grammar.php` — add compilation for LIKE and column comparison predicates.
- `tests/Unit/BuilderTest.php` — add focused predicate tests.
- `openspec/specs/query-builder/spec.md` — update baseline after implementation.
