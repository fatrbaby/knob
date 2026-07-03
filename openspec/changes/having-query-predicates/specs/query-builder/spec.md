## MODIFIED Requirements

### Requirement: HAVING query predicates

The query builder SHALL support common aggregate filtering helpers beyond basic `having()` and `havingRaw()`.

#### Scenario: OR HAVING predicates

- **WHEN** a caller adds `orHaving()` or `orHavingRaw()` after another HAVING clause
- **THEN** the generated SQL SHALL connect it with `OR`
- **AND** bindings SHALL remain in placeholder order

#### Scenario: HAVING range and null predicates

- **WHEN** a caller adds HAVING between, null, or not-null predicates
- **THEN** the generated SQL SHALL compile the corresponding `BETWEEN`, `IS NULL`, or `IS NOT NULL` predicate

#### Scenario: HAVING column comparisons

- **WHEN** a caller compares two aggregate or selected expressions with `havingColumn()`
- **THEN** the generated SQL SHALL compare the two identifiers or raw expressions without adding value bindings
