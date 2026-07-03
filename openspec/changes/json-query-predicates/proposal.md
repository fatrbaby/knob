## Why

Many application schemas store flexible metadata in JSON columns. Today callers must use `whereRaw()` for JSON filtering, which loses portability and pushes binding details to the caller.

## What Changes

- Add a small, portable JSON predicate surface:
  - `whereJsonContains()`
  - `orWhereJsonContains()`
  - `whereJsonDoesntContain()`
  - `orWhereJsonDoesntContain()`
  - `whereJsonLength()`
  - `orWhereJsonLength()`
- Add driver-specific compilation for supported databases.

## Non-Goals

- Do not attempt to cover every JSON path operator exposed by each database.
- Do not add JSON mutation/update helpers.
- Do not guarantee identical indexing or performance behavior across drivers.

## Impact

- `src/Builder.php` — add JSON where records.
- `src/Grammars/*Grammar.php` — compile per-driver JSON predicates.
- `tests/Unit/GrammarTest.php` and `tests/Unit/BuilderTest.php` — add SQL and binding tests.
- `openspec/specs/query-builder/spec.md` — update baseline after implementation.
