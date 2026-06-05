## Design

### Predicate OR Variants

`whereSub()` and `whereExists()` already compile records with a `boolean` field through the shared where compiler. The new OR methods reuse the same record types and set `boolean` to `OR`.

### Join Subquery Variants

`joinSub()` currently inlines the `INNER JOIN` shape. A small internal helper accepts the join type and keeps subquery normalization and join-clause normalization in one place. `leftJoinSub()` uses that helper with `LEFT JOIN`.

### Scalar Reads

`value($column)` clones the current builder, selects only the requested column, limits to one row, and returns the first column from the result. `doesntExist()` is the boolean inverse of `exists()`.

## Decisions

- Do not add `rightJoinSub()` or `crossJoinSub()` in this change; `leftJoinSub()` is the highest-frequency missing join-subquery variant.
- Do not change SQL grammar compilation. The existing `boolean` and binding machinery already supports these additions.
- Keep `value()` return type as `mixed` so PDO scalar behavior is preserved.
