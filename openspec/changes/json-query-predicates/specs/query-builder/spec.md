## MODIFIED Requirements

### Requirement: JSON query predicates

The query builder SHALL support a small portable set of JSON filtering helpers where the active driver can compile them safely.

#### Scenario: JSON contains predicates

- **WHEN** a caller filters a JSON column/path for a contained value
- **THEN** the generated SQL SHALL use the active driver's JSON containment syntax
- **AND** the searched value SHALL be bound as a parameter

#### Scenario: JSON length predicates

- **WHEN** a caller filters by JSON array/object length
- **THEN** the generated SQL SHALL compare the active driver's JSON length expression against a bound value

#### Scenario: Unsupported JSON predicates

- **WHEN** a requested JSON predicate cannot be safely compiled for the active driver
- **THEN** the builder SHALL throw a clear runtime exception
