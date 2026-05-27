## 1. Builder Changes

- [x] 1.1 Modify `where()` method signature to accept `Closure` as first argument
- [x] 1.2 Add `'group'` type handling in `where()` when Closure is passed
- [x] 1.3 Add `orWhere(Closure)` support (same pattern as `where(Closure)`)

## 2. Grammar Changes

- [x] 2.1 Add `compileWhereGroup()` method in `Grammar.php`
- [x] 2.2 Add `'group'` case to `compileWhere()` match expression
- [x] 2.3 Ensure bindings from nested wheres are correctly merged into parent bindings

## 3. Tests

- [x] 3.1 Add test for basic nested AND group
- [x] 3.2 Add test for nested group with OR conditions inside
- [x] 3.3 Add test for multiple nested groups at same level
- [x] 3.4 Add test for deeply nested groups (2 levels)
- [x] 3.5 Add test for bindings order preservation across groups
- [x] 3.6 Add test for whereIn inside group
- [x] 3.7 Add test for whereBetween inside group
- [x] 3.8 Add test for whereNull / whereNotNull inside group
- [x] 3.9 Add test for whereExists inside group
- [x] 3.10 Add test for group at top level (no outer conditions)

## 4. Code Style

- [x] 4.1 Run `./vendor/bin/pint` to fix style issues in modified files
