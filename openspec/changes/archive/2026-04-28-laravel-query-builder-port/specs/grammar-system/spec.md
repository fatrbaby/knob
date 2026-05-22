## ADDED Requirements

### Requirement: Per-driver grammar compilation
The system SHALL compile SQL strings specific to each database driver.

#### Scenario: PostgreSQL limit offset
- **WHEN** PostgresGrammar compiles `limit(10)->offset(20)`
- **THEN** the system SHALL generate `LIMIT 10 OFFSET 20`

#### Scenario: MySQL limit offset
- **WHEN** MySqlGrammar compiles `limit(10)->offset(20)`
- **THEN** the system SHALL generate `LIMIT 10 OFFSET 20`

#### Scenario: SQLServer limit offset
- **WHEN** SqlServerGrammar compiles `limit(10)->offset(20)`
- **THEN** the system SHALL generate `OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY`

#### Scenario: SQLite limit offset
- **WHEN** SqliteGrammar compiles `limit(10)->offset(20)`
- **THEN** the system SHALL generate `LIMIT 10 OFFSET 20`

### Requirement: Parameter binding
The Grammar SHALL handle SQL parameter binding to prevent injection.

#### Scenario: String parameter binding
- **WHEN** Grammar compiles `where('name', 'John')`
- **THEN** the system SHALL generate `WHERE name = ?` with bindings `['John']`

#### Scenario: Multiple parameter binding
- **WHEN** Grammar compiles `where('age', '>', 18)->where('status', 'active')`
- **THEN** the system SHALL generate `WHERE age > ? AND status = ?` with bindings `[18, 'active']`

#### Scenario: IN clause parameter binding
- **WHEN** Grammar compiles `whereIn('id', [1, 2, 3])`
- **THEN** the system SHALL generate `WHERE id IN (?, ?, ?)` with bindings `[1, 2, 3]`

### Requirement: Quote escaping
The Grammar SHALL properly quote identifiers for each database.

#### Scenario: PostgreSQL identifier quotes
- **WHEN** PostgresGrammar compiles `select('user name')`
- **THEN** the system SHALL quote identifiers as `"user name"`

#### Scenario: MySQL identifier quotes
- **WHEN** MySqlGrammar compiles `select('user name')`
- **THEN** the system SHALL quote identifiers as `` `user name` ``

### Requirement: Join clause compilation
The Grammar SHALL compile JOIN conditions correctly per driver.

#### Scenario: Basic join condition
- **WHEN** Grammar compiles `join('posts', 'users.id', '=', 'posts.user_id')`
- **THEN** the system SHALL generate `INNER JOIN posts ON users.id = posts.user_id`

#### Scenario: Left join
- **WHEN** Grammar compiles `leftJoin('posts', 'users.id', '=', 'posts.user_id')`
- **THEN** the system SHALL generate `LEFT JOIN posts ON users.id = posts.user_id`

### Requirement: Grammar factory
The system SHALL create the appropriate Grammar instance based on PDO driver.

#### Scenario: PostgreSQL grammar selection
- **WHEN** `Knob::getDriver()` returns `Driver::PostgreSQL`
- **THEN** the system SHALL use `PostgresGrammar`

#### Scenario: MySQL grammar selection
- **WHEN** `Knob::getDriver()` returns `Driver::MySQL`
- **THEN** the system SHALL use `MySqlGrammar`

#### Scenario: SQLite grammar selection
- **WHEN** `Knob::getDriver()` returns `Driver::SQLite`
- **THEN** the system SHALL use `SqliteGrammar`

#### Scenario: SQLServer grammar selection
- **WHEN** `Knob::getDriver()` returns `Driver::SQLServer`
- **THEN** the system SHALL use `SqlServerGrammar`
