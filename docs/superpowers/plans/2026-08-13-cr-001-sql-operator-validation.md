# CR-001 SQL Operator Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reject untrusted SQL operators at every non-raw query-builder entry point while preserving shorthand, null, binding, and compilation behavior.

**Architecture:** Add one stateless `Knob\SqlOperator` normalizer containing the complete allowlist and diagnostic formatting. Invoke it in `Builder` and `JoinClause` convergence methods before mutating query state; Grammar continues compiling only normalized internal state.

**Tech Stack:** PHP 8.2+, PDO, Pest 4, Composer PSR-4 autoloading.

## Global Constraints

- Allowed operators are exactly `=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `LIKE`, and `ILIKE`.
- Normalize string input with `trim()` followed by `strtoupper()`; reject every non-string value.
- Validation must happen before `Builder` or `JoinClause` state changes.
- Two-argument shorthand, null comparisons, binding order, and raw APIs retain their existing semantics.
- `ILIKE` is compiled for every driver but is not executed by the SQLite regression test.
- Do not add Grammar-level duplicate validation or change identifier quoting.

---

### Task 1: Shared SQL operator normalizer

**Files:**
- Create: `src/SqlOperator.php`
- Create: `tests/Unit/SqlOperatorTest.php`

**Interfaces:**
- Consumes: PHP scalar/object type inspection and `InvalidArgumentException`.
- Produces: `Knob\SqlOperator::normalize(mixed $operator): string`.

- [x] **Step 1: Write failing normalizer tests**

Create Pest datasets/tests that assert all nine allowed operators, whitespace/case normalization, and rejection of `''`, `= ? OR 1=1 --`, `null`, booleans, integers, arrays, and objects. Assert the exception message includes a type-aware original value and the complete allowed list.

- [x] **Step 2: Run the focused test and verify RED**

Run: `vendor/bin/pest tests/Unit/SqlOperatorTest.php`

Expected: failure because `Knob\SqlOperator` does not exist.

- [x] **Step 3: Implement the minimal normalizer**

Create a final class with a private `ALLOWED` constant, strict string check, `trim()`/`strtoupper()`, strict `in_array()`, and a private diagnostic formatter. Throw:

```text
Unsupported SQL operator <typed original>. Allowed operators: =, !=, <>, <, <=, >, >=, LIKE, ILIKE.
```

- [x] **Step 4: Run the focused test and verify GREEN**

Run: `vendor/bin/pest tests/Unit/SqlOperatorTest.php`

Expected: all normalizer tests pass without warnings.

### Task 2: Builder integration

**Files:**
- Modify: `src/Builder.php`
- Modify: `tests/Unit/BuilderTest.php`

**Interfaces:**
- Consumes: `SqlOperator::normalize(mixed $operator): string` from Task 1.
- Produces: normalized operators in simple joins, join subqueries, basic where clauses, column comparisons, scalar subqueries, date/time/year/month predicates, and having clauses.

- [x] **Step 1: Write failing Builder integration tests**

Add tests covering normalized ` like ` and `iLiKe` state/SQL at each convergence point; injection rejection for `where`, `having`, simple `join`, `whereColumn`, `whereSub`, date conditions, and `joinSub`; two-argument shorthand; `=`, `!=`, and `<>` null behavior; and unchanged state after a rejected call.

- [x] **Step 2: Run focused Builder tests and verify RED**

Run: `vendor/bin/pest tests/Unit/BuilderTest.php --filter='operator'`

Expected: assertions fail because unsafe and unnormalized operators are still stored.

- [x] **Step 3: Add validation at Builder convergence points**

Call `SqlOperator::normalize()` after resolving shorthand but before appending state in `normalizeJoinClauses()`, `addWhereClause()`, a new shared private column-comparison helper used by `whereColumn()`/`orWhereColumn()`, `addWhereSubClause()`, `addDateWhereClause()`, and `having()`. For subqueries, normalize the operator before invoking/normalizing the callback so failure cannot trigger callback side effects.

- [x] **Step 4: Run focused Builder tests and verify GREEN**

Run: `vendor/bin/pest tests/Unit/SqlOperatorTest.php tests/Unit/BuilderTest.php`

Expected: all focused tests pass without warnings.

### Task 3: JoinClause integration and execution-level regression

**Files:**
- Modify: `src/JoinClause.php`
- Modify: `tests/Unit/BuilderTest.php`

**Interfaces:**
- Consumes: `SqlOperator::normalize(mixed $operator): string` from Task 1.
- Produces: normalized callback-join `on`/`orOn` and `where`/`orWhere` clauses with preserved null conversion.

- [x] **Step 1: Write failing callback-join and SQLite safety tests**

Add tests for normalization in `on` and value predicates, injection rejection in both callback-join paths, unchanged query state after rejection, and a two-row SQLite query proving `= ? OR 1=1 --` throws before execution rather than returning all rows.

- [x] **Step 2: Run focused tests and verify RED**

Run: `vendor/bin/pest tests/Unit/BuilderTest.php --filter='operator'`

Expected: callback join stores/compiles the malicious operator or does not normalize it.

- [x] **Step 3: Validate before JoinClause mutation**

Normalize in `addOnClause()` before appending. In `addBasicClause()`, first resolve two-argument shorthand, then normalize, then perform `=`, `!=`, `<>` null conversion, and finally append basic state.

- [x] **Step 4: Run focused tests and verify GREEN**

Run: `vendor/bin/pest tests/Unit/SqlOperatorTest.php tests/Unit/BuilderTest.php`

Expected: all focused tests pass, including SQLite execution safety.

### Task 4: Review record and full verification

**Files:**
- Modify: `docs/CODE_REVIEW.md`

**Interfaces:**
- Consumes: verified implementation and test results from Tasks 1–3.
- Produces: completed CR-001 review entry with date, summary, regressions, commands, and raw API boundary.

- [x] **Step 1: Run formatting and full tests**

Run: `vendor/bin/pint --test src/SqlOperator.php src/Builder.php src/JoinClause.php tests/Unit/SqlOperatorTest.php tests/Unit/BuilderTest.php`

Run: `vendor/bin/pest`

Expected: formatting check and entire suite pass; environment-dependent database cases may retain their documented skips.

- [x] **Step 2: Update the CR-001 review entry**

Set status to `已完成` and add processing date `2026-08-13`, change summary, regression coverage, exact verification commands/results, and the explicit decision that raw APIs remain caller-controlled.

- [x] **Step 3: Re-run relevant verification after documentation change**

Run: `vendor/bin/pest tests/Unit/SqlOperatorTest.php tests/Unit/BuilderTest.php && vendor/bin/pest`

Expected: both focused and full suites pass.
