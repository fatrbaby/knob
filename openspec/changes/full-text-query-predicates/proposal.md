## Why

Simple LIKE predicates are not enough for text search over larger content. Knob should provide a minimal full-text query helper for applications that use database-native full-text indexes.

## What Changes

- Add a focused full-text predicate surface:
  - `whereFullText()`
  - `orWhereFullText()`
- Compile per-driver SQL where a practical native implementation exists.
- Provide clear unsupported behavior for drivers/configurations that cannot support it.

## Non-Goals

- Do not build a search abstraction with ranking, highlighting, stemming configuration, or index management.
- Do not add external search-engine integrations.
- Do not duplicate every Laravel full-text option.

## Impact

- `src/Builder.php` — add full-text where records.
- `src/Grammars/*Grammar.php` — compile per-driver full-text SQL.
- `tests/Unit/GrammarTest.php` and `tests/Unit/BuilderTest.php` — add SQL and binding tests.
- `openspec/specs/query-builder/spec.md` — update baseline after implementation.
