# 全量代码审查记录

- 审查日期：2026-08-13
- 审查范围：`../src`、`../tests`、Composer 配置、测试与质量工具配置
- 当前状态：P2 实现已完成，CR-018 待托管 CI 验证
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
- 状态：已完成
- 位置：`src/Builder.php:1153`、`src/Builder.php:649`、`src/JoinClause.php:67`
- 问题：`where`、`having` 和 join 条件中的操作符直接拼入 SQL。
- 已验证：传入 `= ? OR 1=1 --` 可以绕过原条件并返回全部记录。
- 建议：使用受支持操作符白名单；非法操作符抛出明确异常。
- 完成标准：所有相关入口共用校验逻辑，并补充恶意操作符回归测试。
- 处理人：Codex
- 处理日期：2026-08-13
- 变更摘要：新增共享 `SqlOperator` 规范化器，白名单限制 `=`、`!=`、`<>`、`<`、`<=`、`>`、`>=`、`LIKE`、`ILIKE`；在 Builder 与 JoinClause 的所有动态操作符汇合点进入状态前完成校验和规范化。
- 回归测试：覆盖全部允许操作符、大小写/空白规范化、非字符串及注入载荷拒绝、where/having/simple join/callback join/joinSub/whereColumn/whereSub/日期条件入口、失败后状态不变、子查询回调不执行、两参数简写、null 比较、绑定顺序和 SQLite 执行级拦截。
- 验证命令：`vendor/bin/pint --test src/SqlOperator.php src/Builder.php src/JoinClause.php tests/Unit/SqlOperatorTest.php tests/Unit/BuilderTest.php` 通过；`vendor/bin/pest tests/Unit/SqlOperatorTest.php tests/Unit/BuilderTest.php` 通过（145 tests，292 assertions）；`vendor/bin/pest` 通过（200 passed，3 skipped，378 assertions）。
- 决策备注：`whereRaw()`、`orWhereRaw()`、`havingRaw()` 等明确接收原始 SQL 的 API 不应用操作符白名单，继续由调用方承担安全责任；`ILIKE` 仅保证编译，是否可执行由目标数据库决定。

### CR-002：修正 UNION 与排序、分页的编译顺序

- 优先级：P0
- 状态：已完成
- 位置：`src/Grammars/Grammar.php:53-67`
- 问题：当前先输出 `ORDER BY/LIMIT/OFFSET`，再输出 `UNION`。
- 已验证：SQLite 报错 `ORDER BY clause should come after UNION ALL`。
- 建议：定义主查询、union 子查询和整体排序分页的明确语义，必要时对子查询加括号。
- 完成标准：四种数据库均有 SQL 编译测试，至少 SQLite 有执行测试。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：将 UNION/UNION ALL 编译到整体 ORDER BY、LIMIT、OFFSET 之前，并同步调整 union 与 order 绑定顺序；需要局部分页或嵌套 union 的分支通过派生表隔离作用域；compound query 的结构化排序使用输出列名，SQL Server 无显式排序时使用输出序号。
- 回归测试：覆盖 MySQL、PostgreSQL、SQLite、SQL Server 的整体排序分页与限定排序列编译、SQL Server 默认 compound 排序、union/order 参数绑定顺序、SQLite 整体排序分页执行，以及 union 分支局部分页执行。
- 验证命令：`vendor/bin/pest tests/Unit/GrammarTest.php tests/Unit/BuilderTest.php`；`vendor/bin/pest`。
- 决策备注：外层 Builder 的 ORDER BY/LIMIT/OFFSET 作用于完整 compound query；结构化限定排序名会收敛为 compound 输出列名，复杂表达式应使用 `orderByRaw()`。传给 `union()`/`unionAll()` 的 Builder 自身分页仅作用于该分支，必要时编译为带固定内部别名的派生表；未分页分支的排序没有可观察语义，编译时省略，以兼容 SQL Server 派生表限制。

### CR-003：统一并完善标识符引用

- 优先级：P0
- 状态：已完成
- 位置：`src/Grammars/Grammar.php:95`、`:110`、`:192-205`、`:260-267`、`:337-341`、`:488-518`
- 问题：WHERE、JOIN、ORDER BY、HAVING 等位置未正确引用列名；`main.x` 会被编译为单个标识符 `"main.x"`。
- 已验证：保留字列 `order` 用于 `where()` 或 `orderBy()` 时产生 SQL 语法错误。
- 建议：实现统一的 `wrapIdentifier()`，支持 `schema.table`、`table.column`、`table.*`、别名；原始表达式使用独立类型。
- 完成标准：所有非 raw API 统一引用标识符，并覆盖保留字和限定名称测试。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：新增统一 `Grammar::wrapIdentifier()`，逐段引用限定标识符并支持 `*` 与显式 `AS` 别名；SELECT、FROM、JOIN、WHERE、GROUP BY、HAVING、ORDER BY、日期条件及写操作统一使用该入口，`selectRaw()` 改用明确的 raw 组件。
- 回归测试：四种方言覆盖限定列、限定表、通配符、别名和全部结构化子句；覆盖所有结构化 where 类型、raw API 保持原文与绑定，以及 SQLite 保留字列的真实查询执行。
- 验证命令：`vendor/bin/pint --test src/Builder.php src/Grammars/Grammar.php src/Grammars/MySqlGrammar.php src/Grammars/SqlServerGrammar.php src/Grammars/SqliteGrammar.php tests/Unit/BuilderTest.php tests/Unit/GrammarTest.php`；`vendor/bin/pest`。
- 决策备注：只有名称包含 `Raw` 的公开 API 以及内部生成的子查询 SQL 绕过标识符引用；普通 `select()` 不再根据括号猜测表达式，表达式应显式使用 `selectRaw()`。

### CR-004：修正无 FROM 查询生成非法 SQL

- 优先级：P0
- 状态：已完成
- 位置：`src/Builder.php:1062-1076`、`src/Grammars/Grammar.php:33-35`
- 问题：`Knob::query()->selectRaw('1')` 生成 `SELECT 1 FROM`。
- 建议：无表时不要生成 `from` 组件，或让 Grammar 判断实际表名。
- 完成标准：无 FROM 的常量/表达式查询能正确编译和执行。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：Grammar 根据 from 组件中的实际表名决定是否输出 FROM，并让 `selectRaw()` 以明确的 raw 组件编译常量和表达式。
- 回归测试：覆盖 `selectRaw('1')` 精确编译为 `SELECT 1`，以及 SQLite 执行 `SELECT 1 AS result`。
- 验证命令：`vendor/bin/pest tests/Unit/BuilderTest.php`；`vendor/bin/pest`。
- 决策备注：无表查询仍支持 LIMIT/OFFSET 等目标方言允许的后续组件；只省略不存在的 FROM 子句。

### CR-005：补齐 SQLite 的 OFFSET 和 TRUNCATE 方言

- 优先级：P0
- 状态：已完成
- 位置：`src/Grammars/SqliteGrammar.php:12-20`、`src/Grammars/Grammar.php:655-658`
- 问题：SQLite 不支持单独的 `OFFSET n`，也不支持 `TRUNCATE`。
- 已验证：两个公开 API 在 SQLite 上均执行失败。
- 建议：offset-only 使用 `LIMIT -1 OFFSET n`；truncate 对 SQLite 使用 `DELETE FROM`，并明确自增序列处理语义。
- 完成标准：增加 SQLite 执行级回归测试。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：SQLite offset-only 编译为 `LIMIT -1 OFFSET n`，truncate 编译为 `DELETE FROM <table>`。
- 回归测试：覆盖 offset-only 精确 SQL 与 SQLite 真实分页执行，并覆盖 truncate 删除全部行后继续使用原自增序列。
- 验证命令：`vendor/bin/pest tests/Unit/GrammarTest.php tests/Unit/BuilderTest.php`；`vendor/bin/pest`。
- 决策备注：SQLite truncate 仿真实现只删除行，不操作 `sqlite_sequence`，因此自增值不会重置；这避免额外语句和隐式序列副作用。

### CR-006：统一 selectSub 的可空别名契约

- 优先级：P1
- 状态：已完成
- 位置：`src/Builder.php:57-72`、`src/Grammars/Grammar.php:74-81`
- 问题：API 声明别名可空，但编译时无条件传给 `quoteIdentifier(string)`，导致 `TypeError`。
- 建议：将别名改为必填，或在空别名时省略 `AS`。
- 完成标准：接口声明、实现和测试行为一致。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：保留可空别名 API；`selectSub()` 未提供别名时只编译括号子查询，不再输出 `AS`。
- 回归测试：覆盖原始 SQL、闭包和可复用 Builder 三种无别名输入，并保留有别名行为。
- 验证命令：`vendor/bin/pest tests/Unit/BuilderTest.php tests/Unit/GrammarTest.php tests/Unit/KnobTest.php`。
- 决策备注：省略别名是 SQL 支持的合法形式，也避免对现有公开签名作破坏性调整。

### CR-007：忽略或拒绝空条件组

- 优先级：P1
- 状态：已完成
- 位置：`src/Builder.php:189-220`、`src/Grammars/Grammar.php:382-410`
- 问题：空闭包条件会生成 `WHERE ` 或空的 `NOT` 表达式。
- 建议：Builder 阶段不记录空组，或抛出参数异常。
- 完成标准：覆盖空 `where`、`orWhere`、`whereNot` 条件组。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：Builder 在闭包执行后检查嵌套条件，空组不进入查询状态，避免生成空 WHERE 或 NOT 表达式。
- 回归测试：覆盖空 `where`、`orWhere`、`whereNot`、`orWhereNot`，并验证调用前后 SQL 与绑定不变。
- 验证命令：`vendor/bin/pest tests/Unit/BuilderTest.php tests/Unit/GrammarTest.php tests/Unit/KnobTest.php`。
- 决策备注：采用忽略空组的策略，便于调用方按条件动态构造查询。

### CR-008：修正复杂查询的分页总数

- 优先级：P1
- 状态：已完成
- 位置：`src/Builder.php:895-907`、`src/Builder.php:1041-1059`
- 问题：分组、distinct、union 查询直接替换为 `COUNT(*)`，总数可能取到首个分组的行数。
- 已验证：两个分组的数据返回 `total = 3`，正确值应为 `2`。
- 建议：复杂查询使用 `SELECT COUNT(*) FROM (<原查询>) AS aggregate`。
- 完成标准：覆盖 groupBy、distinct、union、having 分页总数。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：分页总数统一对移除外层排序和分页后的原查询建立派生表，再执行外层 `COUNT(*)`，保留原查询语义和绑定。
- 回归测试：覆盖 groupBy、HAVING 过滤及绑定、DISTINCT、UNION ALL 的分页总数。
- 验证命令：`vendor/bin/pest tests/Unit/BuilderTest.php tests/Unit/GrammarTest.php tests/Unit/KnobTest.php`。
- 决策备注：统一使用派生表计数，避免按查询形态分支后再次遗漏复杂组合。

### CR-009：校验分页参数

- 优先级：P1
- 状态：已完成
- 位置：`src/Builder.php:1041-1059`
- 问题：`paginate(0, 1)` 触发 `DivisionByZeroError`，负页码会产生非法偏移。
- 建议：要求 `perPage >= 1` 且 `page >= 1`。
- 完成标准：非法输入抛出明确的参数异常并有测试。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：`paginate()` 在构造或执行查询前要求 `perPage >= 1` 且 `page >= 1`，否则抛出 `InvalidArgumentException`。
- 回归测试：覆盖页大小和页码为零、负数的四种非法输入。
- 验证命令：`vendor/bin/pest tests/Unit/BuilderTest.php tests/Unit/GrammarTest.php tests/Unit/KnobTest.php`。
- 决策备注：校验发生在计数和数据查询之前，非法调用不会访问数据库。

### CR-010：支持 value/pluck 的限定列名和别名

- 优先级：P1
- 状态：已完成
- 位置：`src/Builder.php:981-1017`
- 问题：查询 `t.name` 后按 `$row['t.name']` 读取，而 PDO 通常返回键 `name`。
- 已验证：`value('t.name')` 返回 null 并产生 warning，`pluck('t.name')` 返回空数组。
- 建议：解析最终字段名，或为内部查询生成稳定别名。
- 完成标准：覆盖限定列名、显式别名和表达式。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：`value()`、`pluck()` 根据限定名或显式别名解析 PDO 结果键；读取已选 raw 表达式的别名时保留原选择，不用结构化列覆盖表达式。
- 回归测试：覆盖限定值列、限定键列、显式 `AS` 别名，以及已选 raw 表达式作为值列或键列。
- 验证命令：`vendor/bin/pest tests/Unit/BuilderTest.php tests/Unit/GrammarTest.php tests/Unit/KnobTest.php`。
- 决策备注：表达式仍通过 `selectRaw()` 明确声明；结果读取只解析稳定的最终别名，不猜测任意 SQL 表达式。

### CR-011：校验 BETWEEN 参数数量

- 优先级：P1
- 状态：已完成
- 位置：`src/Builder.php:317-367`、`src/Grammars/Grammar.php:310-321`
- 问题：少于两个值会产生 undefined array key，并绑定 null；多余值被静默忽略。
- 建议：Builder 层要求恰好两个值。
- 完成标准：不足和超出两个值均有明确行为和测试。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：四个 BETWEEN 入口共用 Builder 校验，值数量不等于两个时在修改查询状态前抛出 `InvalidArgumentException`。
- 回归测试：覆盖 `whereBetween`、`orWhereBetween`、`whereNotBetween`、`orWhereNotBetween` 的不足和超出输入，覆盖两元素关联数组归一化，并验证失败后状态不变。
- 验证命令：`vendor/bin/pest tests/Unit/BuilderTest.php tests/Unit/GrammarTest.php tests/Unit/KnobTest.php`。
- 决策备注：拒绝静默截断多余值，也不允许用 null 补足缺失值。

### CR-012：明确危险写操作的保护策略

- 优先级：P1
- 状态：已完成
- 位置：`src/Builder.php:745-868`、`src/Grammars/Grammar.php:618-658`
- 问题：`update([])` 生成空 SET；`update()`、`delete()` 默认允许无 WHERE 全表操作；`truncate()` 无表时静默返回 false，与其他写方法契约不一致。
- 建议：拒绝空更新；评估默认禁止无条件写操作，提供显式 `allowFullTable()`；统一无表错误行为。
- 完成标准：危险边界被明确记录并有回归测试。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：拒绝空更新；无 WHERE 的 update/delete 默认抛错，只有显式调用 `allowFullTable()` 才放行；无表 truncate 与其他写方法统一抛错；授权状态随 Builder 克隆保留。
- 回归测试：覆盖空更新、默认拦截且数据不变、显式全表更新/删除、三种无表写操作和授权克隆。
- 验证命令：`vendor/bin/pest tests/Unit/BuilderTest.php tests/Unit/GrammarTest.php tests/Unit/KnobTest.php`。
- 决策备注：`truncate()` 本身已明确表达全表清空意图，不额外要求 `allowFullTable()`；该授权仅适用于 update/delete。

### CR-013：增强事务异常与嵌套事务处理

- 优先级：P1
- 状态：已完成
- 位置：`src/Knob.php:42-70`
- 问题：无条件开启、提交、回滚；嵌套事务会失败，回滚异常可能覆盖原始异常。
- 建议：检查 `inTransaction()` 和开始事务结果；明确嵌套策略，可使用 savepoint；保留原始异常上下文。
- 完成标准：覆盖成功、回调异常、提交异常、嵌套调用。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：检查开始和提交结果；已有事务时按数据库方言使用 savepoint 隔离嵌套作用域；失败时只回滚当前作用域；回滚再次失败时抛出组合信息，并以原异常作为 previous context。
- 回归测试：覆盖成功提交及返回值、回调异常回滚、嵌套失败仅回滚内层、开始失败、提交返回失败、提交异常、回滚二次失败的异常上下文，以及四种数据库的 savepoint/rollback 命令。
- 验证命令：`vendor/bin/pest tests/Unit/KnobTest.php`；`vendor/bin/pest tests/Unit/BuilderTest.php tests/Unit/GrammarTest.php tests/Unit/KnobTest.php`。
- 决策备注：MySQL、PostgreSQL、SQLite 使用 `SAVEPOINT`/`ROLLBACK TO SAVEPOINT`/`RELEASE SAVEPOINT`；SQL Server 使用 `SAVE TRANSACTION`/`ROLLBACK TRANSACTION`，且不执行其不支持的 release。

### CR-014：清理 PHPStan Level 8 错误

- 优先级：P2
- 状态：已完成
- 位置：`../src`、`../tests`
- 问题：当前有 134 个错误，主要是数组值类型/shape、Collection 泛型、未知 PDO 驱动、nullable 返回和测试动态属性。
- 特别关注：`src/Knob.php:73-80` 未处理未知 PDO 驱动；`src/Grammars/MySqlGrammar.php:35` 的 `preg_replace()` 可能返回 null。
- 建议：建立 `phpstan.neon`，为查询组件定义可复用 shape/type alias，逐步清零且不使用 baseline 掩盖问题。
- 完成标准：`phpstan analyse src tests --level=8` 无错误。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：新增 Level 8 `phpstan.neon`；为 Builder/Grammar 查询组件、写操作组件、绑定、Collection 与测试数据定义可复用泛型和 array shape；显式处理未知 PDO 驱动、正则拆分失败、JSON 编码失败及测试中的动态属性和不完整 match。
- 验证命令：`composer analyse` 通过，无 baseline、忽略规则或行级抑制。
- 决策备注：PHPStan 使用 `--debug` 强制单进程执行，避免受限环境中并行分析器创建本地 TCP 服务；内存上限固定为 1 GiB。

### CR-015：统一 PHP 版本与开发依赖要求

- 优先级：P2
- 状态：已完成
- 位置：`../composer.json`、`../README.md`、`../.github/workflows/ci.yml`
- 问题：项目声明 PHP 8.2+，但 Pest 4.3 和 PHPUnit 12 要求 PHP 8.3+。
- 建议：提高最低 PHP 版本到 8.3，或降级到支持 PHP 8.2 的测试工具。
- 完成标准：CI 在声明的最低 PHP 版本上可完成 `composer install` 和测试。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：最低 PHP 版本统一为 `^8.3`，Composer 平台固定为 PHP 8.3.0 以保证依赖解析兼容最低运行版本，PHPStan 改为直接开发依赖；README 明确支持范围为 PHP 8.3–8.x。
- 验证命令：`composer install --no-interaction`、`composer validate --strict`、`composer ci`；GitHub Actions 覆盖 PHP 8.3、8.4、8.5。
- 决策备注：选择提高最低版本而非降级 Pest，以保持当前测试工具主版本并与 PHPUnit 12 的运行要求一致；作为库项目继续采用不提交 `composer.lock` 的策略，CI 每次验证当前约束的可解依赖集合。

### CR-016：完善 CI 质量门禁

- 优先级：P2
- 状态：已完成
- 位置：`Makefile:43-46`、`../composer.json`
- 问题：`make ci` 使用会修改文件的 `pint`，且缺少 PHPStan、Composer 校验和依赖审计；Composer 没有标准 scripts。
- 建议：CI 使用 `pint --test`，并加入 Composer validate、PHPStan、Pest、Composer audit。
- 完成标准：本地与 CI 使用同一组只读检查命令。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：新增 Composer `test`、`lint`、`analyse`、`ci` scripts；`make ci` 与 GitHub Actions 统一调用 `composer ci`；格式门禁改为 `pint --test`，另保留显式 `make format` 修改入口。
- 验证命令：`composer ci` 依次完成 Composer 严格校验、Pint、PHPStan、Pest 与 Composer audit。
- 决策备注：质量门禁全程只读，依赖审计保留联网要求；本地修复格式必须显式调用 `make format`。

### CR-017：清理测试模板并修复格式

- 优先级：P2
- 状态：已完成
- 位置：`tests/Pest.php:27-43`
- 问题：Pint 检查失败；保留了未使用的示例 expectation 和 `something()` 函数。
- 建议：删除无用模板代码并统一格式。
- 完成标准：`vendor/bin/pint --test` 通过。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：删除未使用的示例 expectation、`something()` 函数及无效 Feature 测试基类绑定；测试共享数据改为显式 fixture，避免动态属性。
- 验证命令：`composer lint` 通过。

### CR-018：建立真实的跨数据库测试矩阵

- 优先级：P2
- 状态：待验证
- 位置：`../tests/Integration/DatabaseSmokeTest.php`
- 问题：默认只执行 SQLite；另外三种数据库测试均跳过，方言目前主要依赖字符串断言。
- 建议：CI 使用 MySQL、PostgreSQL、SQL Server 服务容器，重点覆盖 upsert、日期函数、分页、事务和 union。
- 完成标准：四种数据库的集成测试均在 CI 必跑且不能静默跳过。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：新增独立数据库 CI 作业，以服务容器提供 MySQL 8.4、PostgreSQL 17 和 SQL Server 2022，并与 SQLite 一起执行同一套真实查询流程；流程覆盖排序分页、更新、upsert、年份条件、paginate、union、事务回滚和删除。
- 回归测试：集成测试在本地无 DSN 时仍允许跳过外部数据库；CI 设置 `KNOB_SMOKE_REQUIRED=1`，任何缺失 DSN 都直接失败，避免三种服务数据库静默跳过。
- 验证命令：`vendor/bin/pest tests/Integration/DatabaseSmokeTest.php`；GitHub Actions `databases` 作业执行 `composer test -- tests/Integration`。
- 决策备注：实现与本地可验证部分已完成；本机 Docker 服务不可访问，因此当前保持“待验证”，待托管 CI 中 MySQL、PostgreSQL、SQL Server 容器首次全绿后改为“已完成”。

### CR-019：补齐发布元数据和安全文档

- 优先级：P2
- 状态：已完成
- 位置：`../composer.json`、`../README.md`、仓库根目录
- 问题：声明 Apache-2.0 但缺少 `LICENSE`；README 未说明 raw API、动态标识符和动态操作符的安全边界。
- 建议：添加许可证文件、Composer scripts、raw API 安全说明和支持矩阵。
- 完成标准：发布包包含许可证，README 与实际接口/工具命令一致。
- 处理人：Codex
- 处理日期：2026-09-04
- 变更摘要：新增完整 Apache License 2.0 许可证正文，并排除本机配置、环境文件和 CodeGraph socket 等非发布内容；README 同步 PHP 8.3–8.x、PDO 驱动与四数据库支持矩阵、Composer/Make 开发命令，并明确 raw SQL、`selectSub(string)`、动态标识符和动态操作符的信任边界。
- 验证命令：`composer validate --strict`、`composer archive --format=zip --dir=/tmp --file=knob-p2-review`、`composer ci`。
- 决策备注：结构化值继续使用参数绑定；raw 片段只能来自可信应用代码，外部输入的标识符和操作符必须先通过应用侧映射或白名单。

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
