## Design

HAVING clauses should follow the same internal pattern as WHERE clauses where practical: each record carries a `type` and a `boolean` connector, and grammar compilation is responsible for adding bindings in placeholder order.

`havingColumn()` should compare identifiers without adding value bindings. Null helpers should compile to `IS NULL` and `IS NOT NULL`.

## Decisions

- Keep `having()` backward compatible by treating existing calls as `AND`.
- Keep raw HAVING available as the escape hatch for vendor-specific aggregate expressions.
- Avoid nested HAVING groups in this change.
