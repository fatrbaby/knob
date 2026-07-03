## Design

JSON support should stay intentionally narrow. Callers should pass a column/path expression and a bound value. Grammar classes should handle the database-specific SQL spelling.

Supported behavior should be documented per driver where exact semantics differ. Unsupported driver/path combinations should fail with a clear runtime exception instead of silently compiling unsafe SQL.

## Decisions

- Keep raw JSON SQL available through `whereRaw()` for advanced cases.
- Start with contains and length predicates only.
- Avoid introducing a standalone JSON path parser unless implementation requires it for correctness.
