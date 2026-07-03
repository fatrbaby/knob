## Why

Transactional workflows often need row-level locks to avoid conflicting updates. Knob currently leaves this to raw SQL. A small lock API would cover the common database-native cases while keeping transaction orchestration explicit.

## What Changes

- Add query lock helpers:
  - `lockForUpdate()`
  - `sharedLock()`
  - `lock(string|bool $value)`
- Compile lock clauses at the end of SELECT statements where supported.

## Non-Goals

- Do not manage transactions automatically.
- Do not add ORM-style pessimistic locking helpers.
- Do not normalize every lock mode exposed by every database.

## Impact

- `src/Builder.php` — store selected lock mode.
- `src/Grammars/*Grammar.php` — compile lock syntax by driver.
- `tests/Unit/GrammarTest.php` and `tests/Unit/BuilderTest.php` — add SQL compilation tests.
- `openspec/specs/query-builder/spec.md` — update baseline after implementation.
