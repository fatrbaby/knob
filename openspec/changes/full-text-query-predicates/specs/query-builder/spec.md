## MODIFIED Requirements

### Requirement: Full-text query predicates

The query builder SHALL support a minimal native full-text predicate helper where the active driver can compile it safely.

#### Scenario: Full-text predicate

- **WHEN** a caller filters one or more columns with `whereFullText()`
- **THEN** the generated SQL SHALL use the active driver's native full-text predicate
- **AND** the search string SHALL be bound as a parameter

#### Scenario: OR full-text predicate

- **WHEN** a caller adds `orWhereFullText()` after another predicate
- **THEN** the generated SQL SHALL connect the full-text predicate with `OR`

#### Scenario: Unsupported full-text predicate

- **WHEN** the active driver cannot safely compile a full-text predicate
- **THEN** the builder SHALL throw a clear runtime exception
