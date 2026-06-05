## MODIFIED Requirements

### Requirement: Common query conveniences

The query builder SHALL provide convenience helpers for common subquery predicate, join-subquery, and scalar-read workflows.

#### Scenario: OR subquery predicates

- **WHEN** a caller adds `orWhereSub()`, `orWhereExists()`, or `orWhereNotExists()` after another where clause
- **THEN** the generated SQL SHALL connect the predicate with `OR`
- **AND** subquery bindings SHALL be preserved in placeholder order

#### Scenario: Left join subquery

- **WHEN** a caller uses `leftJoinSub()` with a closure or reusable builder subquery
- **THEN** the generated SQL SHALL compile a `LEFT JOIN (<subquery>) AS <alias>` clause
- **AND** subquery bindings SHALL appear before join-clause and outer where bindings

#### Scenario: Scalar reads and missing-row checks

- **WHEN** a caller uses `value($column)`
- **THEN** the builder SHALL return the first selected column value from the first matching row, or `null` when no row matches
- **WHEN** a caller uses `doesntExist()`
- **THEN** the builder SHALL return the inverse of `exists()`
