## 1. API Design

- [x] 1.1 Add `Knob\JoinClause`
- [x] 1.2 Preserve existing `join()`, `leftJoin()`, and `rightJoin()` signatures
- [x] 1.3 Add callback join support for `join()`, `leftJoin()`, and `rightJoin()`
- [x] 1.4 Add callback join support for `joinSub()`

## 2. Join Clause Methods

- [x] 2.1 Add `on()` and `orOn()`
- [x] 2.2 Add join `where()` and `orWhere()` value predicates
- [x] 2.3 Add join `whereNull()` and `orWhereNull()`
- [x] 2.4 Add join `whereNotNull()` and `orWhereNotNull()`

## 3. Grammar

- [x] 3.1 Compile structured join `on` clauses
- [x] 3.2 Compile structured join value predicates
- [x] 3.3 Compile structured join null predicates
- [x] 3.4 Propagate join-clause bindings in SQL placeholder order

## 4. Tests

- [x] 4.1 Existing simple join tests still pass
- [x] 4.2 Test multi-condition callback join
- [x] 4.3 Test OR join conditions
- [x] 4.4 Test join value predicate bindings
- [x] 4.5 Test join null and not-null predicates
- [x] 4.6 Test callback `joinSub()` with subquery and join-clause bindings

## 5. Baseline

- [x] 5.1 Update `openspec/specs/query-builder/spec.md` after implementation
- [x] 5.2 Run unit tests
