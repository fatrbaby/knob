## MODIFIED Requirements

### Requirement: Query locking

The query builder SHALL support explicit SELECT lock clauses for transactional workflows where the active driver can compile them safely.

#### Scenario: Exclusive row lock

- **WHEN** a caller uses `lockForUpdate()`
- **THEN** the generated SELECT SQL SHALL include the active driver's exclusive row-lock clause

#### Scenario: Shared row lock

- **WHEN** a caller uses `sharedLock()`
- **THEN** the generated SELECT SQL SHALL include the active driver's shared lock clause

#### Scenario: Custom lock clause

- **WHEN** a caller uses `lock(string $value)`
- **THEN** the generated SELECT SQL SHALL include that lock clause in the driver-appropriate position

#### Scenario: Unsupported lock clause

- **WHEN** the active driver cannot safely compile the requested lock
- **THEN** the builder SHALL throw a clear runtime exception
