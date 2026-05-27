## MODIFIED Requirements

### Requirement: Fluent query building

The system SHALL support a consistent subquery input model across scalar subqueries, `IN` subqueries, and `EXISTS` subqueries.

#### Scenario: Select scalar subquery with closure

- **WHEN** user calls `Knob::table('users')->select('name')->selectSub(fn($q) => $q->from('posts')->selectRaw('COUNT(*)')->whereRaw('posts.user_id = users.id'), 'posts_count')`
- **THEN** the system SHALL compile a scalar subquery in the select list aliased as `posts_count`

#### Scenario: Select scalar subquery with reusable builder

- **GIVEN** a builder created as `Knob::query()->from('posts')->selectRaw('COUNT(*)')->whereRaw('posts.user_id = users.id')`
- **WHEN** user passes that builder to `selectSub(..., 'posts_count')`
- **THEN** the system SHALL compile the builder as a scalar subquery and preserve its bindings

### Requirement: Subquery support

The system SHALL accept both `Closure` and `Builder` inputs for supported subquery clauses and SHALL preserve subquery binding order when composing the parent query.

#### Scenario: Where in subquery with closure

- **WHEN** user calls `Knob::table('posts')->whereIn('user_id', fn($q) => $q->select('id')->from('users')->where('status', 'active'))`
- **THEN** the system SHALL generate a `WHERE user_id IN (subquery)` clause and include the subquery bindings in the parent where bindings

#### Scenario: Where not in subquery with reusable builder

- **GIVEN** a builder created as `Knob::query()->select('id')->from('users')->where('status', 'inactive')`
- **WHEN** user calls `Knob::table('posts')->whereNotIn('user_id', $subquery)`
- **THEN** the system SHALL generate a `WHERE user_id NOT IN (subquery)` clause and preserve the builder bindings

#### Scenario: Column comparison against subquery with reusable builder

- **GIVEN** a builder created as `Knob::query()->selectRaw('MAX(score)')->from('scores')->where('scores.user_id', 10)`
- **WHEN** user calls `Knob::table('users')->whereSub('score', '>=', $subquery)`
- **THEN** the system SHALL generate `score >= (subquery)` and merge subquery bindings into the parent where bindings

#### Scenario: Exists subquery with reusable builder

- **GIVEN** a builder created as `Knob::query()->from('posts')->whereRaw('posts.user_id = users.id')->where('published', true)`
- **WHEN** user calls `Knob::table('users')->whereExists($subquery)`
- **THEN** the system SHALL generate `EXISTS (subquery)` and preserve the subquery bindings in order

#### Scenario: Binding order across parent and subquery components

- **WHEN** user composes a query using `selectSub`, `fromSub`, `joinSub`, and `whereExists`, each with their own bindings
- **THEN** `toSqlParts()['bindings']` SHALL follow the same placeholder order as the compiled SQL across select, from, join, and where components
