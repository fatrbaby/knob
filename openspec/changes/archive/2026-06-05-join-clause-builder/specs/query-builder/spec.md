## MODIFIED Requirements

### Requirement: Fluent query building

The system SHALL support callback-based join clause construction for common multi-condition join scenarios while preserving existing simple join calls.

#### Scenario: Multi-condition join

- **WHEN** user calls `join('posts', fn($join) => $join->on('users.id', '=', 'posts.user_id')->whereNull('posts.deleted_at'))`
- **THEN** generated SQL contains `JOIN posts ON users.id = posts.user_id AND posts.deleted_at IS NULL`

#### Scenario: OR join condition

- **WHEN** user calls `join('contacts', fn($join) => $join->on('users.id', '=', 'contacts.user_id')->orOn('users.email', '=', 'contacts.email'))`
- **THEN** generated SQL contains `ON users.id = contacts.user_id OR users.email = contacts.email`

#### Scenario: Join value predicate

- **WHEN** user calls `leftJoin('memberships', fn($join) => $join->on('users.id', '=', 'memberships.user_id')->where('memberships.active', true))`
- **THEN** generated SQL contains `LEFT JOIN memberships ON users.id = memberships.user_id AND memberships.active = ?`
- **AND** join bindings include `[true]`

#### Scenario: Join not-null predicate

- **WHEN** user calls `join('profiles', fn($join) => $join->on('users.id', '=', 'profiles.user_id')->whereNotNull('profiles.verified_at'))`
- **THEN** generated SQL contains `ON users.id = profiles.user_id AND profiles.verified_at IS NOT NULL`

#### Scenario: Callback joinSub

- **WHEN** user joins a subquery with a callback join clause that includes value predicates
- **THEN** generated SQL contains the subquery join and callback ON clauses
- **AND** subquery bindings precede join-clause value bindings in the join binding bucket
