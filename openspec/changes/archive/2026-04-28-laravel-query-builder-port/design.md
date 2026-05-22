## Context

Knob is a lightweight database query builder ported from Laravel. The project is in early stages with basic stubs (Builder, Grammar, Knob facade). The goal is a production-ready query builder without Laravel dependencies.

**Current state:**
- `Knob.php` — static facade with `using()` and `table()` methods
- `Builder.php` — skeleton with empty stub methods
- `Driver.php` — enum for MySQL, PostgreSQL, SQLite, SQLServer
- `Grammar.php` / `PostgresGrammar.php` — empty grammar classes

**Constraints from discussion:**
- PHP 8.2+ with new features
- Pure PDO, no external dependencies
- Lazy/Chainable Collection
- Tier 1 + Tier 2 SQL features (no window functions, no recursive CTEs)

## Goals / Non-Goals

**Goals:**
- Fluent API for building SQL queries
- Multi-driver support via grammar abstraction
- Lazy Collection with map/filter/reduce
- Parameter binding for security
- Query execution (get, first, paginate, insert, update, delete)

**Non-Goals:**
- Full Laravel ORM support (models, relationships, eager loading)
- Tier 3 features (window functions, recursive CTEs)
- Query caching
- Migration system

## Decisions

### 1. Architecture: Builder holds state, Grammar compiles

**Decision:** Builder maintains query state (columns, wheres, joins, etc.). Grammar turns Builder state into SQL string + bindings.

**Why:** Mirrors Laravel's approach which is proven. Separates concerns — Builder handles fluent interface, Grammar handles SQL generation.

**Alternatives considered:**
- Each method directly appends SQL strings — harder to maintain, harder to swap grammars
- Grammar as a pure builder (methods return SQL fragments) — adds complexity to state management

### 2. Mutable Builder (Laravel style)

**Decision:** Methods like `where()` modify the Builder instance and return `$this`.

**Why:** Familiar to Laravel users, simpler to implement, less object churn.

**Alternatives considered:**
- Immutable (returns new instance) — safer for testing, harder to teach, more allocation overhead

### 3. Collection: Lazy evaluation with IteratorAggregate

**Decision:** Collection wraps an Iterator and defers operations until iteration.

**Why:** Memory efficient for large result sets, familiar pattern (Symfony, Laravel).

**Implementation approach:**
```php
class Collection implements IteratorAggregate, Countable, ArrayAccess
{
    private array $items = [];
    private ?callable $callback = null;

    public function __construct(array $items = []) { ... }
    public function map(callable $fn): Collection { ... }
    public function filter(callable $fn): Collection { ... }
    public function getIterator(): Traversable { ... }
}
```

### 4. Grammar per driver with shared base

```
Grammar (abstract base)
├── PostgresGrammar
├── MySqlGrammar
├── SqliteGrammar
└── SqlServerGrammar
```

**Decision:** Each driver has its own Grammar subclass to handle dialect differences.

**Why:** Different databases have different SQL syntax (e.g., LIMIT/OFFSET vs TOP, quote styles).

### 5. PDO connection passed to Builder constructor

**Decision:** `Knob::table('users')` uses the connection set via `Knob::using($pdo)`.

**Why:** Keeps Builder stateless regarding connection, connection managed centrally.

**Alternatives considered:**
- Builder takes connection in constructor — works but differs from Laravel's static approach
- Connection in Knob static — already implemented this way

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| Grammar proliferation (4 drivers × many methods) | Shared base class with default implementations |
| Parameter binding complexity | Centralized bindings array, Grammar handles escaping |
| Mutable state causing issues | Document clearly, provide clone method for safety |

## Open Questions

1. **Transaction support?** — Not discussed yet. `beginTransaction()`, `commit()`, `rollBack()` on Knob?
2. **Raw expression handling** — `DB::raw()` escape hatch for complex SQL?
3. **Pagination return type** — `paginate()` returning `LengthAwarePaginator` or just array with meta?
4. **Exception handling** — How to handle PDO exceptions? Wrap or propagate raw?
