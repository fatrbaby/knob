## 1. Foundation

- [x] 1.1 Implement Driver enum with grammar factory method
- [x] 1.2 Create base Grammar abstract class with compile methods
- [x] 1.3 Create PostgresGrammar implementation
- [x] 1.4 Create MySqlGrammar implementation
- [x] 1.5 Create SqliteGrammar implementation
- [x] 1.6 Create SqlServerGrammar implementation

## 2. Collection

- [x] 2.1 Implement Collection class with IteratorAggregate, Countable, ArrayAccess
- [x] 2.2 Add lazy map operation
- [x] 2.3 Add lazy filter operation
- [x] 2.4 Add first() and last() methods
- [x] 2.5 Add toArray() and toJson() methods

## 3. Builder Core

- [x] 3.1 Refactor Builder to use Grammar instance properly
- [x] 3.2 Implement select() method with column handling
- [x] 3.3 Implement from() method with table/alias
- [x] 3.4 Implement where() with operators (=, >, <, >=, <=, !=, like)
- [x] 3.5 Implement orWhere() method
- [x] 3.6 Implement whereIn() method
- [x] 3.7 Implement whereNotIn() method
- [x] 3.8 Implement whereBetween() method
- [x] 3.9 Implement whereNull() and whereNotNull() methods

## 4. Builder Joins

- [x] 4.1 Implement join() method
- [x] 4.2 Implement leftJoin() method
- [x] 4.3 Implement rightJoin() method
- [x] 4.4 Implement crossJoin() method

## 5. Builder Aggregates & Grouping

- [x] 5.1 Implement count() method
- [x] 5.2 Implement sum() method
- [x] 5.3 Implement avg() method
- [x] 5.4 Implement max() method
- [x] 5.5 Implement min() method
- [x] 5.6 Implement groupBy() method
- [x] 5.7 Implement having() method
- [x] 5.8 Implement havingRaw() method

## 6. Builder Subqueries & Unions

- [x] 6.1 Implement selectSub() method
- [x] 6.2 Implement fromSub() method
- [x] 6.3 Implement whereIn with subquery
- [x] 6.4 Implement joinSub() method
- [x] 6.5 Implement union() method
- [x] 6.6 Implement unionAll() method

## 7. Builder Ordering & Pagination

- [x] 7.1 Implement orderBy() method
- [x] 7.2 Implement orderByDesc() method
- [x] 7.3 Implement latest() method
- [x] 7.4 Implement oldest() method
- [x] 7.5 Implement limit() method
- [x] 7.6 Implement offset() method

## 8. Builder Modifications

- [x] 8.1 Implement insert() method
- [x] 8.2 Implement insertGetId() method
- [x] 8.3 Implement update() method
- [x] 8.4 Implement delete() method
- [x] 8.5 Implement truncate() method

## 9. Query Execution

- [x] 9.1 Implement get() method returning Collection
- [x] 9.2 Implement first() method
- [x] 9.3 Implement pluck() method
- [x] 9.4 Implement exists() method
- [x] 9.5 Implement paginate() method
- [x] 9.6 Wire up PDO statement execution with bindings

## 10. Knob Facade

- [x] 10.1 Extend Knob::table() to accept connection
- [x] 10.2 Add beginTransaction() method
- [x] 10.3 Add commit() method
- [x] 10.4 Add rollBack() method
- [x] 10.5 Add transaction() helper method

## 11. Testing

- [ ] 11.1 Write Grammar compilation tests per driver
- [x] 11.2 Write Collection operation tests
- [x] 11.3 Write Builder fluent interface tests
- [ ] 11.4 Write integration tests with real database
