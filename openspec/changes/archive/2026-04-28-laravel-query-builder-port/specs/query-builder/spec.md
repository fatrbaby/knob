## ADDED Requirements

### Requirement: Fluent query builder
The system SHALL provide a fluent query builder that generates SQL queries via method chaining without Laravel dependencies.

#### Scenario: Basic select query
- **WHEN** user calls `Knob::table('users')->select('name', 'email')->where('status', 'active')`
- **THEN** the system SHALL generate `SELECT name, email FROM users WHERE status = ?` with bindings `['active']`

#### Scenario: Select with multiple where conditions
- **WHEN** user calls `Knob::table('users')->where('status', 'active')->where('age', '>', 18)`
- **THEN** the system SHALL generate `SELECT * FROM users WHERE status = ? AND age > ?` with bindings `['active', 18]`

#### Scenario: Select with or where
- **WHEN** user calls `Knob::table('users')->where('status', 'active')->orWhere('status', 'pending')`
- **THEN** the system SHALL generate `SELECT * FROM users WHERE status = ? OR status = ?` with bindings `['active', 'pending']`

### Requirement: Join operations
The system SHALL support INNER, LEFT, and RIGHT joins between tables.

#### Scenario: Left join
- **WHEN** user calls `Knob::table('users')->select('users.name', 'posts.title')->leftJoin('posts', 'users.id', '=', 'posts.user_id')`
- **THEN** the system SHALL generate `SELECT users.name, posts.title FROM users LEFT JOIN posts ON users.id = posts.user_id`

#### Scenario: Multiple joins
- **WHEN** user calls `Knob::table('users')->join('posts', 'users.id', '=', 'posts.user_id')->join('comments', 'posts.id', '=', 'comments.post_id')`
- **THEN** the system SHALL generate `SELECT * FROM users INNER JOIN posts ON users.id = posts.user_id INNER JOIN comments ON posts.id = comments.post_id`

### Requirement: Subquery support
The system SHALL support subqueries in WHERE IN, FROM, and JOIN clauses.

#### Scenario: Where in subquery
- **WHEN** user calls `Knob::table('posts')->whereIn('user_id', function($q) { $q->select('id')->from('users')->where('active', true); })`
- **THEN** the system SHALL generate `SELECT * FROM posts WHERE user_id IN (SELECT id FROM users WHERE active = ?)`

#### Scenario: From subquery
- **WHEN** user calls `Knob::table()->fromSub(function($q) { $q->select('name')->from('users'); }, 'active_users')`
- **THEN** the system SHALL generate `SELECT * FROM (SELECT name FROM users) AS active_users`

### Requirement: Union queries
The system SHALL support UNION and UNION ALL between queries.

#### Scenario: Basic union
- **WHEN** user calls `Knob::table('users')->where('type', 'admin')->union(Knob::table('users')->where('type', 'superadmin'))`
- **THEN** the system SHALL generate `SELECT * FROM users WHERE type = ? UNION SELECT * FROM users WHERE type = ?`

#### Scenario: Union all
- **WHEN** user calls `Knob::table('users')->unionAll(Knob::table('archived_users'))`
- **THEN** the system SHALL generate `SELECT * FROM users UNION ALL SELECT * FROM archived_users`

### Requirement: Group by and having
The system SHALL support GROUP BY with HAVING conditions.

#### Scenario: Group by with count having
- **WHEN** user calls `Knob::table('posts')->select('user_id')->groupBy('user_id')->having('count', '>', 5)`
- **THEN** the system SHALL generate `SELECT user_id FROM posts GROUP BY user_id HAVING count > ?`

### Requirement: Order and pagination
The system SHALL support ORDER BY, LIMIT, and OFFSET for pagination.

#### Scenario: Order by descending
- **WHEN** user calls `Knob::table('users')->orderBy('created_at', 'desc')->limit(10)`
- **THEN** the system SHALL generate `SELECT * FROM users ORDER BY created_at DESC LIMIT ?`

#### Scenario: Offset pagination
- **WHEN** user calls `Knob::table('users')->limit(10)->offset(20)`
- **THEN** the system SHALL generate `SELECT * FROM users LIMIT ? OFFSET ?` with bindings `[10, 20]`

### Requirement: Insert, update, delete
The system SHALL support data modification queries.

#### Scenario: Insert with values
- **WHEN** user calls `Knob::table('users')->insert(['name' => 'John', 'email' => 'john@example.com'])`
- **THEN** the system SHALL generate `INSERT INTO users (name, email) VALUES (?, ?)` with bindings `['John', 'john@example.com']`

#### Scenario: Update with where
- **WHEN** user calls `Knob::table('users')->where('id', 1)->update(['name' => 'Jane'])`
- **THEN** the system SHALL generate `UPDATE users SET name = ? WHERE id = ?` with bindings `['Jane', 1]`

#### Scenario: Delete with where
- **WHEN** user calls `Knob::table('users')->where('id', 1)->delete()`
- **THEN** the system SHALL generate `DELETE FROM users WHERE id = ?` with bindings `[1]`

### Requirement: Aggregation functions
The system SHALL support count, sum, avg, max, min as aggregate methods.

#### Scenario: Count all
- **WHEN** user calls `Knob::table('users')->count()`
- **THEN** the system SHALL generate `SELECT COUNT(*) FROM users` and return the count

#### Scenario: Sum column
- **WHEN** user calls `Knob::table('orders')->where('status', 'completed')->sum('amount')`
- **THEN** the system SHALL generate `SELECT SUM(amount) FROM orders WHERE status = ?` and return the sum
