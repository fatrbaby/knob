## ADDED Requirements

### Requirement: Get all results
The system SHALL execute queries and return all results as a Collection.

#### Scenario: Get all rows
- **WHEN** user calls `Knob::table('users')->get()`
- **THEN** the system SHALL execute `SELECT * FROM users` and return a Collection of all rows

#### Scenario: Get with columns
- **WHEN** user calls `Knob::table('users')->select('name', 'email')->get()`
- **THEN** the system SHALL execute `SELECT name, email FROM users` and return Collection of rows

### Requirement: Get single result
The system SHALL execute queries and return a single row.

#### Scenario: First row
- **WHEN** user calls `Knob::table('users')->where('id', 1)->first()`
- **THEN** the system SHALL execute `SELECT * FROM users WHERE id = ? LIMIT 1` and return a single row or null

### Requirement: Pluck values
The system SHALL return a Collection of single column values.

#### Scenario: Pluck single column
- **WHEN** user calls `Knob::table('users')->pluck('name')`
- **THEN** the system SHALL return Collection of name values

#### Scenario: Pluck with key
- **WHEN** user calls `Knob::table('users')->pluck('name', 'id')`
- **THEN** the system SHALL return Collection with id as key and name as value

### Requirement: Existence check
The system SHALL provide an exists method for checking row existence.

#### Scenario: Exists returns boolean
- **WHEN** user calls `Knob::table('users')->where('status', 'active')->exists()`
- **THEN** the system SHALL return true if at least one row matches, false otherwise

### Requirement: Insert execution
The system SHALL execute INSERT queries and return success status.

#### Scenario: Insert single row
- **WHEN** user calls `Knob::table('users')->insert(['name' => 'John', 'email' => 'john@example.com'])`
- **THEN** the system SHALL execute `INSERT INTO users (name, email) VALUES (?, ?)` and return true on success

#### Scenario: Insert multiple rows
- **WHEN** user calls `Knob::table('users')->insert([['name' => 'John'], ['name' => 'Jane']])`
- **THEN** the system SHALL execute `INSERT INTO users (name) VALUES (?), (?)` with bindings `['John', 'Jane']`

#### Scenario: Insert get last id
- **WHEN** user calls `Knob::table('users')->insertGetId(['name' => 'John'])`
- **THEN** the system SHALL execute INSERT and return the last inserted ID

### Requirement: Update execution
The system SHALL execute UPDATE queries and return affected rows.

#### Scenario: Update with where
- **WHEN** user calls `Knob::table('users')->where('id', 1)->update(['name' => 'Jane'])`
- **THEN** the system SHALL execute `UPDATE users SET name = ? WHERE id = ?` and return number of affected rows

### Requirement: Delete execution
The system SHALL execute DELETE queries and return affected rows.

#### Scenario: Delete with where
- **WHEN** user calls `Knob::table('users')->where('id', 1)->delete()`
- **THEN** the system SHALL execute `DELETE FROM users WHERE id = ?` and return number of affected rows

### Requirement: Pagination
The system SHALL support pagination returning results with metadata.

#### Scenario: Simple pagination
- **WHEN** user calls `Knob::table('users')->paginate(15)`
- **THEN** the system SHALL return Collection with items, total count, per_page, current_page

#### Scenario: Paginate with offset
- **WHEN** user calls `Knob::table('users')->paginate(15, 2)`
- **THEN** the system SHALL return page 2 with 15 items per page
