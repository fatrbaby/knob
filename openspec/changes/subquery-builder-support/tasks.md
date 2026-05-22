## 1. Spec Alignment

- [x] 1.1 Update the `query-builder` change spec to define unified subquery inputs for scalar, `IN`, and `EXISTS` subqueries
- [x] 1.2 Validate that the proposed scenarios cover both `Closure` and `Builder` subquery inputs

## 2. Builder API

- [x] 2.1 Add a private subquery normalization path in `src/Builder.php`
- [x] 2.2 Expand `selectSub` to accept query-builder based subqueries while keeping raw SQL string compatibility
- [x] 2.3 Expand `whereSub` to accept `Closure` and `Builder` subqueries through the shared normalization path
- [x] 2.4 Expand `whereIn` and `whereNotIn` to accept either value arrays or query-builder based subqueries
- [x] 2.5 Update `whereExists` and `whereNotExists` to accept either `Closure` or `Builder` inputs

## 3. Grammar Compilation

- [x] 3.1 Add or refactor grammar compilation paths for `IN (subquery)` and `NOT IN (subquery)`
- [x] 3.2 Ensure subquery bindings are merged into the correct binding buckets in SQL placeholder order
- [x] 3.3 Verify scalar subquery bindings in `selectSub` participate in `toSqlParts()` and `toSql()` correctly

## 4. Tests

- [x] 4.1 Add test for `selectSub` with closure-defined subquery
- [x] 4.2 Add test for `selectSub` with reusable builder subquery
- [x] 4.3 Add test for `whereIn` with subquery input
- [x] 4.4 Add test for `whereNotIn` with subquery input
- [x] 4.5 Add test for `whereSub` with reusable builder input
- [x] 4.6 Add test for `whereExists` with reusable builder input
- [x] 4.7 Add test for mixed parent and subquery binding order across select/from/join/where components
