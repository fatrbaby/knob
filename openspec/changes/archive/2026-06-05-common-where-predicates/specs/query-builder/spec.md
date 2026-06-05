## MODIFIED Requirements

### Requirement: Fluent query building

The system SHALL provide first-class helpers for common portable where predicates without requiring raw SQL.

#### Scenario: LIKE predicate

- **WHEN** user calls `Knob::table('users')->whereLike('name', 'A%')`
- **THEN** generated SQL contains `name LIKE ?`
- **AND** bindings contain `['A%']`

#### Scenario: NOT LIKE predicate

- **WHEN** user calls `Knob::table('users')->whereNotLike('email', '%@example.test')`
- **THEN** generated SQL contains `email NOT LIKE ?`
- **AND** bindings contain `['%@example.test']`

#### Scenario: OR LIKE predicates

- **WHEN** user calls `Knob::table('users')->whereLike('name', 'A%')->orWhereLike('email', '%@example.com')`
- **THEN** generated SQL contains `name LIKE ? OR email LIKE ?`

#### Scenario: Column comparison predicate

- **WHEN** user calls `Knob::table('users')->whereColumn('created_at', '>', 'updated_at')`
- **THEN** generated SQL contains `created_at > updated_at`
- **AND** no binding is added for the compared columns

#### Scenario: OR column comparison predicate

- **WHEN** user calls `Knob::table('users')->where('status', 'active')->orWhereColumn('created_at', '<', 'updated_at')`
- **THEN** generated SQL contains `status = ? OR created_at < updated_at`

#### Scenario: OR variants for existing predicates

- **WHEN** user composes `orWhereNotIn()`, `orWhereBetween()`, or `orWhereNotBetween()` after an existing where clause
- **THEN** the generated SQL uses `OR`
- **AND** bindings preserve the SQL placeholder order

#### Scenario: Common predicates inside nested groups

- **WHEN** user calls `where(fn($q) => $q->whereLike('name', 'A%')->orWhereColumn('created_at', '>', 'updated_at'))`
- **THEN** generated SQL wraps those predicates in parentheses and preserves their boolean operators
