# CR-001 SQL 操作符校验设计

## 背景

`Builder` 和 `JoinClause` 接收调用方提供的 SQL 操作符，并由 `Grammar` 直接将其拼入 SQL。攻击者可以传入 `= ? OR 1=1 --` 等内容，改变查询语义并绕过原条件。

CR-001 要求所有相关入口共用校验逻辑，拒绝非法操作符，并为恶意输入补充回归测试。本设计只处理动态操作符，不处理标识符引用（CR-003）或 raw API 文档（CR-019）。

## 目标与非目标

### 目标

- 所有非 raw API 的动态操作符均经过同一白名单校验。
- 在条件进入 Builder 或 JoinClause 状态前拒绝非法输入。
- 接受操作符的大小写和首尾空白变体，并保存规范形式。
- 保持两参数简写、null 比较和参数绑定的现有语义。
- 覆盖评审载荷及所有动态操作符入口，防止旁路。

### 非目标

- 不限制 `whereRaw()`、`havingRaw()` 等明确接收原始 SQL 的 API；这些 API 继续由调用方承担 SQL 安全责任。
- 不实现跨数据库的 `ILIKE` 模拟。该操作符允许生成，但只有原生支持它的数据库可以成功执行。
- 不在 Grammar 中重复校验内部条件数组。
- 不修改列名、表名或限定标识符的引用方式。

## 方案选择

采用共享规范化器并在 Builder 入口校验。

备选方案包括仅在 Grammar 编译时校验，以及入口校验后再由 Grammar 防御性复验。仅在 Grammar 校验会延迟失败并允许 Builder 保存危险状态；双层校验则会在当前封闭的条件构造路径中引入重复逻辑。因此，本次选择入口校验：尽早失败，同时保持单一策略源。

## 操作符策略

新增无状态内部类 `Knob\SqlOperator`：

```php
final class SqlOperator
{
    private const ALLOWED = [
        '=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'ILIKE',
    ];

    public static function normalize(mixed $operator): string;
}
```

`normalize()` 遵循以下规则：

1. 只接受字符串；非字符串输入均拒绝。
2. 对输入依次执行 `trim()` 和 `strtoupper()`。
3. 使用严格比较检查规范结果是否位于白名单中。
4. 返回规范化后的操作符。
5. 空字符串或白名单外输入抛出 `InvalidArgumentException`。

非法输入的异常消息采用以下格式：

```text
Unsupported SQL operator "<原始值>". Allowed operators: =, !=, <>, <, <=, >, >=, LIKE, ILIKE.
```

字符串以带引号的原值显示；`null`、布尔值、数字和其他类型使用明确的类型化描述。异常消息只用于诊断，不参与 SQL。

## 数据流与状态保证

```text
公开查询 API
  -> 处理参数简写并确定实际 operator
  -> SqlOperator::normalize()
  -> 追加条件状态
  -> Grammar 编译已验证的规范操作符
```

校验必须发生在修改 Builder 或 JoinClause 状态之前。调用方捕获异常后，对象保持调用前状态。

对于 `where('age', 18)` 等两参数形式，先把操作符确定为 `=`，再校验；数值 `18` 是绑定值，不作为操作符处理。

null 转换在操作符规范化之后执行：

- `where('x', '=', null)` 生成 `IS NULL`。
- `where('x', '!=', null)` 和 `where('x', '<>', null)` 生成 `IS NOT NULL`。
- `where('x', 'like', null)` 保持基本条件，生成 `x LIKE ?` 并绑定 null。

## 接入点

所有接受动态操作符的内部汇合点调用 `SqlOperator::normalize()`：

- `Builder::normalizeJoinClauses()`：简单 `join`、`leftJoin`、`rightJoin`、`joinSub` 和 `leftJoinSub`。
- `Builder::addWhereClause()`：`where` 和 `orWhere`。
- `Builder::whereColumn()` 与 `Builder::orWhereColumn()`：抽取共享私有方法，避免重复校验和条件构造。
- `Builder::addWhereSubClause()`：`whereSub` 和 `orWhereSub`。
- `Builder::addDateWhereClause()`：日期、时间、年份、月份条件及其 `or` 版本。
- `Builder::having()`。
- `JoinClause::addOnClause()`：回调 join 的 `on` 和 `orOn`。
- `JoinClause::addBasicClause()`：回调 join 的 `where` 和 `orWhere`。

`whereLike()`、`whereBetween()` 等专用方法不接收调用方提供的操作符，因此无需接入。Grammar 可以直接输出条件数组中的操作符，因为这些数组只能通过上述库内入口构造。

## 数据库兼容性

通用白名单为：

```text
=, !=, <>, <, <=, >, >=, LIKE, ILIKE
```

输入 ` like `、`Like` 或 `iLiKe` 分别规范为 `LIKE` 或 `ILIKE`。`ILIKE` 在所有驱动下均可编译；非 PostgreSQL 驱动执行时是否支持由数据库决定，本次不重写为 `LOWER()` 或其他表达式。

## 测试设计

### SqlOperator 单元测试

- 逐一接受白名单中的全部操作符。
- 验证大小写和首尾空白规范化。
- 拒绝空字符串、非字符串和白名单外内容。
- 拒绝 `= ? OR 1=1 --` 等注入载荷。
- 验证异常类型，以及消息包含原始非法值和完整允许列表。

### Builder 与 JoinClause 接入测试

- 每个内部汇合点至少覆盖一个合法操作符。
- 对 `where`、`having`、简单 join 和回调 join 传入注入载荷，断言调用时立即抛出 `InvalidArgumentException`。
- 覆盖 `whereColumn`、`whereSub`、日期条件及 joinSub，确认没有旁路。
- 验证两参数简写和 `=`, `!=`, `<>` 的 null 比较行为不回归。
- 验证条件数组与生成 SQL 均使用规范化后的 `LIKE`、`ILIKE`。
- 验证校验失败后对象状态未改变。

### SQLite 执行级安全回归

建立至少包含两行数据的表，通过评审中已验证的恶意操作符构造查询，断言在 SQL 执行前抛出 `InvalidArgumentException`，不能返回全部记录。`ILIKE` 只进行编译测试，不在 SQLite 执行。

## 验收标准

- CR-001 列出的 `where`、`having` 和 join 条件全部使用共享校验逻辑。
- 所有额外发现的动态操作符入口同样受保护。
- 非法输入抛出信息明确的 `InvalidArgumentException`。
- 合法输入的现有 SQL 与绑定顺序不回归。
- `LIKE` 和 `ILIKE` 的大小写、首尾空白变体被规范化。
- 执行以下命令通过：

```bash
vendor/bin/pest tests/Unit/SqlOperatorTest.php tests/Unit/BuilderTest.php
vendor/bin/pest
```

实现完成并验证后，将 `docs/CODE_REVIEW.md` 中 CR-001 的状态改为“已完成”，并补充处理日期、变更摘要、回归测试、验证命令和 raw API 边界说明。
