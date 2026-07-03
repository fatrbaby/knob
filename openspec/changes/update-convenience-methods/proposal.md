## Why

Common write paths often need atomic-ish counter updates or insert/update behavior without hand-writing raw arithmetic expressions. Knob has `update()` and `upsert()`, but a small set of convenience methods would cover the recurring cases without expanding into a full ORM-style mutation API.

## What Changes

- Add update helpers:
  - `increment()`
  - `decrement()`
  - `updateOrInsert()`
- Keep these helpers table-scoped and query-builder based.

## Non-Goals

- Do not add model-style timestamps, events, casts, or mass-assignment behavior.
- Do not implement every Laravel write helper.

## Impact

- `src/Builder.php` — add public write helpers.
- `src/Grammars/Grammar.php` — support arithmetic update expressions if needed.
- `tests/Unit/BuilderTest.php` — add SQLite execution tests and grammar-focused tests where driver syntax differs.
- `openspec/specs/query-builder/spec.md` — update baseline after implementation.
