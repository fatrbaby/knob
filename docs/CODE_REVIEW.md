# 全量代码审查记录

- 审查日期：2026-08-13
- 审查范围：`../src`、`../tests`、Composer 配置、测试与质量工具配置
- 当前状态：待逐项处理
- 状态约定：`待处理`、`处理中`、`待验证`、`已完成`、`不处理`

## 验证基线

| 检查项 | 审查结果 |
| --- | --- |
| Pest | 166 passed，3 skipped，300 assertions |
| PHP 语法检查 | 通过 |
| Composer 配置校验 | 通过 |
| Composer 依赖安全审计 | 未发现已知漏洞 |
| Pint | 失败：`../tests/Pest.php` 格式不合规 |
| PHPStan Level 8 | 134 个错误 |
| 跨数据库集成测试 | 仅 SQLite 实际运行；MySQL、PostgreSQL、SQL Server 跳过 |

> 审查时工作区已有未提交修改。本记录只描述当前磁盘状态，不代表已提交版本。

## 问题清单

### CR-001：限制 SQL 操作符，避免 SQL 注入

- 优先级：P0
- 状态：待处理
- 位置：`src/Builder.php:1153`、`src/Builder.php:649`、`src/JoinClause.php:67`
- 问题：`where`、`having` 和 join 条件中的操作符直接拼入 SQL。
- 已验证：传入 `= ? OR 1=1 --` 可以绕过原条件并返回全部记录。
- 建议：使用受支持操作符白名单；非法操作符抛出明确异常。
- 完成标准：所有相关入口共用校验逻辑，并补充恶意操作符回归测试。

### CR-002：修正 UNION 与排序、分页的编译顺序

- 优先级：P0
- 状态：待处理
- 位置：`src/Grammars/Grammar.php:53-67`
- 问题：当前先输出 `ORDER BY/LIMIT/OFFSET`，再输出 `UNION`。
- 已验证：SQLite 报错 `ORDER BY clause should come after UNION ALL`。
- 建议：定义主查询、union 子查询和整体排序分页的明确语义，必要时对子查询加括号。
- 完成标准：四种数据库均有 SQL 编译测试，至少 SQLite 有执行测试。

### CR-003：统一并完善标识符引用

- 优先级：P0
- 状态：待处理
- 位置：`src/Grammars/Grammar.php:95`、`:110`、`:192-205`、`:260-267`、`:337-341`、`:488-518`
- 问题：WHERE、JOIN、ORDER BY、HAVING 等位置未正确引用列名；`main.x` 会被编译为单个标识符 `"main.x"`。
- 已验证：保留字列 `order` 用于 `where()` 或 `orderBy()` 时产生 SQL 语法错误。
- 建议：实现统一的 `wrapIdentifier()`，支持 `schema.table`、`table.column`、`table.*`、别名；原始表达式使用独立类型。
- 完成标准：所有非 raw API 统一引用标识符，并覆盖保留字和限定名称测试。

### CR-004：修正无 FROM 查询生成非法 SQL

- 优先级：P0
- 状态：待处理
- 位置：`src/Builder.php:1062-1076`、`src/Grammars/Grammar.php:33-35`
- 问题：`Knob::query()->selectRaw('1')` 生成 `SELECT 1 FROM`。
- 建议：无表时不要生成 `from` 组件，或让 Grammar 判断实际表名。
- 完成标准：无 FROM 的常量/表达式查询能正确编译和执行。

### CR-005：补齐 SQLite 的 OFFSET 和 TRUNCATE 方言

- 优先级：P0
- 状态：待处理
- 位置：`src/Grammars/SqliteGrammar.php:12-20`、`src/Grammars/Grammar.php:655-658`
- 问题：SQLite 不支持单独的 `OFFSET n`，也不支持 `TRUNCATE`。
- 已验证：两个公开 API 在 SQLite 上均执行失败。
- 建议：offset-only 使用 `LIMIT -1 OFFSET n`；truncate 对 SQLite 使用 `DELETE FROM`，并明确自增序列处理语义。
- 完成标准：增加 SQLite 执行级回归测试。

### CR-006：统一 selectSub 的可空别名契约

- 优先级：P1
- 状态：待处理
- 位置：`src/Builder.php:57-72`、`src/Grammars/Grammar.php:74-81`
- 问题：API 声明别名可空，但编译时无条件传给 `quoteIdentifier(string)`，导致 `TypeError`。
- 建议：将别名改为必填，或在空别名时省略 `AS`。
- 完成标准：接口声明、实现和测试行为一致。

### CR-007：忽略或拒绝空条件组

- 优先级：P1
- 状态：待处理
- 位置：`src/Builder.php:189-220`、`src/Grammars/Grammar.php:382-410`
- 问题：空闭包条件会生成 `WHERE ` 或空的 `NOT` 表达式。
- 建议：Builder 阶段不记录空组，或抛出参数异常。
- 完成标准：覆盖空 `where`、`orWhere`、`whereNot` 条件组。

### CR-008：修正复杂查询的分页总数

- 优先级：P1
- 状态：待处理
- 位置：`src/Builder.php:895-907`、`src/Builder.php:1041-1059`
- 问题：分组、distinct、union 查询直接替换为 `COUNT(*)`，总数可能取到首个分组的行数。
- 已验证：两个分组的数据返回 `total = 3`，正确值应为 `2`。
- 建议：复杂查询使用 `SELECT COUNT(*) FROM (<原查询>) AS aggregate`。
- 完成标准：覆盖 groupBy、distinct、union、having 分页总数。

### CR-009：校验分页参数

- 优先级：P1
- 状态：待处理
- 位置：`src/Builder.php:1041-1059`
- 问题：`paginate(0, 1)` 触发 `DivisionByZeroError`，负页码会产生非法偏移。
- 建议：要求 `perPage >= 1` 且 `page >= 1`。
- 完成标准：非法输入抛出明确的参数异常并有测试。

### CR-010：支持 value/pluck 的限定列名和别名

- 优先级：P1
- 状态：待处理
- 位置：`src/Builder.php:981-1017`
- 问题：查询 `t.name` 后按 `$row['t.name']` 读取，而 PDO 通常返回键 `name`。
- 已验证：`value('t.name')` 返回 null 并产生 warning，`pluck('t.name')` 返回空数组。
- 建议：解析最终字段名，或为内部查询生成稳定别名。
- 完成标准：覆盖限定列名、显式别名和表达式。

### CR-011：校验 BETWEEN 参数数量

- 优先级：P1
- 状态：待处理
- 位置：`src/Builder.php:317-367`、`src/Grammars/Grammar.php:310-321`
- 问题：少于两个值会产生 undefined array key，并绑定 null；多余值被静默忽略。
- 建议：Builder 层要求恰好两个值。
- 完成标准：不足和超出两个值均有明确行为和测试。

### CR-012：明确危险写操作的保护策略

- 优先级：P1
- 状态：待处理
- 位置：`src/Builder.php:745-868`、`src/Grammars/Grammar.php:618-658`
- 问题：`update([])` 生成空 SET；`update()`、`delete()` 默认允许无 WHERE 全表操作；`truncate()` 无表时静默返回 false，与其他写方法契约不一致。
- 建议：拒绝空更新；评估默认禁止无条件写操作，提供显式 `allowFullTable()`；统一无表错误行为。
- 完成标准：危险边界被明确记录并有回归测试。

### CR-013：增强事务异常与嵌套事务处理

- 优先级：P1
- 状态：待处理
- 位置：`src/Knob.php:42-70`
- 问题：无条件开启、提交、回滚；嵌套事务会失败，回滚异常可能覆盖原始异常。
- 建议：检查 `inTransaction()` 和开始事务结果；明确嵌套策略，可使用 savepoint；保留原始异常上下文。
- 完成标准：覆盖成功、回调异常、提交异常、嵌套调用。

### CR-014：清理 PHPStan Level 8 错误

- 优先级：P2
- 状态：待处理
- 位置：`../src`、`../tests`
- 问题：当前有 134 个错误，主要是数组值类型/shape、Collection 泛型、未知 PDO 驱动、nullable 返回和测试动态属性。
- 特别关注：`src/Knob.php:73-80` 未处理未知 PDO 驱动；`src/Grammars/MySqlGrammar.php:35` 的 `preg_replace()` 可能返回 null。
- 建议：建立 `phpstan.neon`，为查询组件定义可复用 shape/type alias，逐步清零且不使用 baseline 掩盖问题。
- 完成标准：`phpstan analyse src tests --level=8` 无错误。

### CR-015：统一 PHP 版本与开发依赖要求

- 优先级：P2
- 状态：待处理
- 位置：`composer.json:25-29`、`../composer.lock`
- 问题：项目声明 PHP 8.2+，但 Pest 4.3 和 PHPUnit 12 要求 PHP 8.3+。
- 建议：提高最低 PHP 版本到 8.3，或降级到支持 PHP 8.2 的测试工具。
- 完成标准：CI 在声明的最低 PHP 版本上可完成 `composer install` 和测试。

### CR-016：完善 CI 质量门禁

- 优先级：P2
- 状态：待处理
- 位置：`Makefile:43-46`、`../composer.json`
- 问题：`make ci` 使用会修改文件的 `pint`，且缺少 PHPStan、Composer 校验和依赖审计；Composer 没有标准 scripts。
- 建议：CI 使用 `pint --test`，并加入 Composer validate、PHPStan、Pest、Composer audit。
- 完成标准：本地与 CI 使用同一组只读检查命令。

### CR-017：清理测试模板并修复格式

- 优先级：P2
- 状态：待处理
- 位置：`tests/Pest.php:27-43`
- 问题：Pint 检查失败；保留了未使用的示例 expectation 和 `something()` 函数。
- 建议：删除无用模板代码并统一格式。
- 完成标准：`vendor/bin/pint --test` 通过。

### CR-018：建立真实的跨数据库测试矩阵

- 优先级：P2
- 状态：待处理
- 位置：`../tests/Integration/DatabaseSmokeTest.php`
- 问题：默认只执行 SQLite；另外三种数据库测试均跳过，方言目前主要依赖字符串断言。
- 建议：CI 使用 MySQL、PostgreSQL、SQL Server 服务容器，重点覆盖 upsert、日期函数、分页、事务和 union。
- 完成标准：四种数据库的集成测试均在 CI 必跑且不能静默跳过。

### CR-019：补齐发布元数据和安全文档

- 优先级：P2
- 状态：待处理
- 位置：`../composer.json`、`../README.md`、仓库根目录
- 问题：声明 Apache-2.0 但缺少 `LICENSE`；README 未说明 raw API、动态标识符和动态操作符的安全边界。
- 建议：添加许可证文件、Composer scripts、raw API 安全说明和支持矩阵。
- 完成标准：发布包包含许可证，README 与实际接口/工具命令一致。

## 建议处理顺序

1. CR-001～CR-005：安全问题和确定会生成非法 SQL 的问题。
2. CR-006～CR-013：API 边界、分页、写操作和事务正确性。
3. CR-014～CR-019：类型质量、CI、跨数据库验证和发布准备。

## 单项处理记录模板

处理某一项时，在对应条目下追加：

```text
- 处理人：
- 处理日期：
- 变更摘要：
- 回归测试：
- 验证命令：
- 决策备注：
```
