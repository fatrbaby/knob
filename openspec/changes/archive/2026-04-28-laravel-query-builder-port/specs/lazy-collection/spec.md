## ADDED Requirements

### Requirement: Lazy evaluation
The Collection SHALL defer execution of operations until iteration.

#### Scenario: Map is not executed until iteration
- **WHEN** user creates `$collection = $query->get()->map(fn($r) => expensiveOperation($r))`
- **THEN** the `expensiveOperation` SHALL NOT be called until the Collection is iterated

#### Scenario: Filter is not executed until iteration
- **WHEN** user creates `$collection = $query->get()->filter(fn($r) => $r->active)`
- **THEN** the filter callback SHALL NOT be called until the Collection is iterated

### Requirement: Chainable operations
The Collection SHALL support method chaining for transform operations.

#### Scenario: Map returns new Collection
- **WHEN** user calls `$collection->map(fn($r) => $r['name'])`
- **THEN** the system SHALL return a new Collection instance

#### Scenario: Filter returns new Collection
- **WHEN** user calls `$collection->filter(fn($r) => $r->active)`
- **THEN** the system SHALL return a new Collection instance

#### Scenario: Chained map and filter
- **WHEN** user calls `$collection->map(...)->filter(...)`
- **THEN** the system SHALL chain both operations in order

### Requirement: First and last
The Collection SHALL provide methods to get first/last items.

#### Scenario: First item
- **WHEN** user calls `$collection->first()`
- **THEN** the system SHALL return the first item or null if empty

#### Scenario: Last item
- **WHEN** user calls `$collection->last()`
- **THEN** the system SHALL return the last item or null if empty

### Requirement: Array access
The Collection SHALL implement ArrayAccess for bracket notation access.

#### Scenario: Offset exists
- **WHEN** user checks `$collection[0]`
- **THEN** the system SHALL return the item at index 0 or null

#### Scenario: Offset set
- **WHEN** user sets `$collection[0] = $item`
- **THEN** the system SHALL store the item at index 0

### Requirement: Countable
The Collection SHALL implement Countable to report item count.

#### Scenario: Count items
- **WHEN** user calls `count($collection)`
- **THEN** the system SHALL return the number of items in the collection

### Requirement: IteratorAggregate
The Collection SHALL implement IteratorAggregate for iteration.

#### Scenario: Foreach iteration
- **WHEN** user iterates with `foreach ($collection as $item)`
- **THEN** the system SHALL yield each item in order

### Requirement: Conversion methods
The Collection SHALL provide toArray and toJson conversion methods.

#### Scenario: To array
- **WHEN** user calls `$collection->toArray()`
- **THEN** the system SHALL return a plain PHP array

#### Scenario: To JSON
- **WHEN** user calls `$collection->toJson()`
- **THEN** the system SHALL return a JSON string
