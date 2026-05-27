## ADDED Requirements

### Requirement: where clause grouping via closure

Builder SHALL support grouping where conditions using a closure. The closure receives a Builder instance, and conditions added within it SHALL be wrapped in parentheses.

#### Scenario: Basic nested AND group
- **WHEN** user calls `Knob::table('users')->where('status', '=', 'active')->where(fn($q) => $q->where('type', 'A')->orWhere('type', 'B'))`
- **THEN** generated SQL is `SELECT * FROM users WHERE status = 'active' AND (type = 'A' OR type = 'B')`

#### Scenario: Nested group with AND conditions inside
- **WHEN** user calls `Knob::table('users')->where(fn($q) => $q->where('a', 1)->where('b', 2))`
- **THEN** generated SQL is `SELECT * FROM users WHERE (a = 1 AND b = 2)`

#### Scenario: Multiple nested groups at same level
- **WHEN** user calls `Knob::table('users')->where(fn($q) => $q->where('a', 1)->orWhere('b', 2))->where(fn($q) => $q->where('c', 3)->orWhere('d', 4))`
- **THEN** generated SQL is `SELECT * FROM users WHERE (a = 1 OR b = 2) AND (c = 3 OR d = 4)`

#### Scenario: Deeply nested groups (2 levels)
- **WHEN** user calls `Knob::table('users')->where('x', 1)->where(fn($q) => $q->where(fn($r) => $r->where('a', 'A')->orWhere('b', 'B'))->where('y', 2))`
- **THEN** generated SQL is `SELECT * FROM users WHERE x = 1 AND ((a = 'A' OR b = 'B') AND y = 2)`

#### Scenario: Nested group preserves bindings order
- **WHEN** user calls `Knob::table('users')->where('a', 1)->where(fn($q) => $q->where('b', 2)->orWhere('c', 3))->where('d', 4)`
- **THEN** bindings are `[1, 2, 3, 4]` in that order

#### Scenario: whereIn inside group
- **WHEN** user calls `Knob::table('users')->where(fn($q) => $q->whereIn('id', [1, 2, 3]))`
- **THEN** generated SQL is `SELECT * FROM users WHERE (id IN (?, ?, ?))`
- **AND** bindings are `[1, 2, 3]`

#### Scenario: whereBetween inside group
- **WHEN** user calls `Knob::table('users')->where(fn($q) => $q->whereBetween('age', [18, 30]))`
- **THEN** generated SQL is `SELECT * FROM users WHERE (age BETWEEN ? AND ?)`

#### Scenario: whereNull inside group
- **WHEN** user calls `Knob::table('users')->where(fn($q) => $q->whereNull('deleted_at')->orWhereNotNull('active'))`
- **THEN** generated SQL is `SELECT * FROM users WHERE (deleted_at IS NULL OR active IS NOT NULL)`

#### Scenario: whereExists inside group
- **WHEN** user calls `Knob::table('users')->where(fn($q) => $q->whereExists(fn($sub) => $sub->from('posts', 'p')->whereRaw('p.user_id = users.id')))`
- **THEN** SQL contains `WHERE (EXISTS (SELECT * FROM posts p WHERE p.user_id = users.id))`

#### Scenario: Group at top level (no outer conditions)
- **WHEN** user calls `Knob::table('users')->where(fn($q) => $q->where('a', 1)->orWhere('b', 2))`
- **THEN** generated SQL is `SELECT * FROM users WHERE (a = 1 OR b = 2)`
