## 1. Builder Changes

- [x] 1.1 Add `whereLike()` and `orWhereLike()`
- [x] 1.2 Add `whereNotLike()` and `orWhereNotLike()`
- [x] 1.3 Add `whereColumn()` and `orWhereColumn()`
- [x] 1.4 Add `orWhereNotIn()`
- [x] 1.5 Add `orWhereBetween()` and `orWhereNotBetween()`

## 2. Grammar Changes

- [x] 2.1 Add `like` where type compilation
- [x] 2.2 Add `column` where type compilation
- [x] 2.3 Verify OR variants reuse existing binding order

## 3. Tests

- [x] 3.1 Test LIKE and OR LIKE SQL and bindings
- [x] 3.2 Test NOT LIKE and OR NOT LIKE SQL and bindings
- [x] 3.3 Test column comparisons without bindings
- [x] 3.4 Test `orWhereNotIn()` with array values and subquery values
- [x] 3.5 Test `orWhereBetween()` and `orWhereNotBetween()` bindings
- [x] 3.6 Test these predicates inside grouped where clauses

## 4. Baseline

- [x] 4.1 Update `openspec/specs/query-builder/spec.md` after implementation
- [x] 4.2 Run unit tests
