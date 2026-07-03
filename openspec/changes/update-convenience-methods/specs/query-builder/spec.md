## MODIFIED Requirements

### Requirement: Update convenience methods

The query builder SHALL provide focused write helpers for common counter and conditional write workflows.

#### Scenario: Increment and decrement

- **WHEN** a caller increments or decrements a numeric column
- **THEN** the builder SHALL execute an update that changes the column relative to its current value
- **AND** it SHALL return the affected row count

#### Scenario: Increment or decrement with extra values

- **WHEN** a caller provides additional update values
- **THEN** the builder SHALL update those columns in the same statement

#### Scenario: Update or insert

- **WHEN** a row matching the provided attributes exists
- **THEN** `updateOrInsert()` SHALL update it with the provided values
- **WHEN** no matching row exists
- **THEN** `updateOrInsert()` SHALL insert a row containing attributes merged with values
