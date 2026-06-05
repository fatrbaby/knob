## Why

Knob already supports the core query shapes used in everyday application code. A few common conveniences still require raw SQL or manual result handling, which makes otherwise simple queries more verbose than they need to be.

Common examples include:

- `status = ? OR EXISTS (...)`
- `score >= (...) OR score IS NULL`
- left joining an aggregate subquery
- reading a single scalar value
- checking that no rows match a query

Adding focused helpers for these cases keeps callers on the fluent, bound-query path without introducing broader driver-specific features.

## What Changes

- Add OR variants for existing subquery predicates:
  - `orWhereSub()`
  - `orWhereExists()`
  - `orWhereNotExists()`
- Add `leftJoinSub()` for common aggregate or filtered subquery joins.
- Add scalar/read convenience methods:
  - `value()`
  - `doesntExist()`
- Preserve current binding order across select, from, join, where, and union components.

## Capabilities

### Modified Capabilities

- `query-builder`: expands common subquery predicate, join-subquery, and scalar-read convenience APIs while preserving existing SQL compilation semantics.

## Impact

- `src/Builder.php` — add public convenience methods and share internals where needed.
- `tests/Unit/BuilderTest.php` — add focused SQL and execution tests.
- `openspec/specs/query-builder/spec.md` — update baseline after implementation.
