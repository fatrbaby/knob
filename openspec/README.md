# OpenSpec Workflow

This repository uses OpenSpec for spec-driven changes.

## Directory Layout

- `openspec/specs/`: Baseline capabilities that are true on `main`
- `openspec/changes/<change-id>/`: Proposed/active changes
  - `proposal.md`: Why + what changes
  - `design.md`: Design trade-offs and decisions
  - `tasks.md`: Implementation checklist
  - `.openspec.yaml`: metadata

## Standard Process

1. Create a change folder under `openspec/changes/<change-id>/`.
2. Write `proposal.md`, `design.md`, and `tasks.md`.
3. Implement code and tests.
4. Keep `tasks.md` in sync with implementation status.
5. After merge, fold stable behavior into `openspec/specs/` baseline.

## Change-ID Naming

Use lowercase kebab-case, e.g.:

- `where-clause-grouping`
- `subquery-binding-propagation`
- `to-sql-interpolation`

## Notes For This Project

- Prefer behavior-first tests in `tests/Unit/BuilderTest.php`.
- Keep SQL generation semantics consistent across all grammars.
- `Builder::toSql()` returns interpolated SQL string.
- `Builder::toSqlParts()` returns structured details (`sql`, `bindings`, components) for internal tooling/tests.
