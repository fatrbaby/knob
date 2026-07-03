## Why

Knob has basic `having()` and `havingRaw()` support, but common aggregate filters still require raw SQL when they need OR connectors, range checks, null checks, or column-to-column comparisons. These cases are frequent in reports and grouped list pages.

## What Changes

- Add focused HAVING helpers:
  - `orHaving()`
  - `orHavingRaw()`
  - `havingBetween()`
  - `orHavingBetween()`
  - `havingNull()`
  - `orHavingNull()`
  - `havingNotNull()`
  - `orHavingNotNull()`
  - `havingColumn()`
  - `orHavingColumn()`
- Preserve existing binding order across WHERE, GROUP BY raw bindings, HAVING, ORDER BY raw bindings, and UNION.

## Non-Goals

- Do not implement the full Laravel HAVING surface.
- Do not add nested HAVING groups unless a later query need justifies it.

## Impact

- `src/Builder.php` — add HAVING helper records.
- `src/Grammars/Grammar.php` — compile additional HAVING types.
- `tests/Unit/BuilderTest.php` — add SQL and binding-order coverage.
- `openspec/specs/query-builder/spec.md` — update baseline after implementation.
