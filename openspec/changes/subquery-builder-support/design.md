## Context

Knob 目前已经具备几种子查询能力，但它们是逐个方法追加出来的：

- `fromSub` 和 `joinSub` 接受 `Closure`
- `whereSub` 只接受 `Closure`
- `whereExists` / `whereNotExists` 只接受 `Closure`
- `selectSub` 只接受原始 SQL 字符串
- `whereIn` / `whereNotIn` 只接受值数组

这导致两个明显问题。第一，调用方无法复用一个已经组装好的 `Builder` 作为子查询。第二，同样属于“子查询输入”的能力，被拆成了几套不兼容的调用约定。仓库当前已有 `Subquery Binding Propagation` 基线能力，说明绑定传播已经被视为稳定行为；因此新设计必须复用现有绑定收集顺序，不能引入额外的分支语义。

## Goals / Non-Goals

**Goals:**

- 统一子查询相关 API 的输入形式，让 `Closure` 与 `Builder` 都能作为标准子查询来源
- 让 `selectSub`、`whereSub`、`whereIn`、`whereNotIn`、`whereExists`、`whereNotExists` 共享同一套子查询规范化逻辑
- 保证所有新增子查询入口都按父查询编译顺序合并 bindings
- 保持现有简单数组 `whereIn` 和字符串 `selectSub` 用法继续可用

**Non-Goals:**

- 不在本次变更中引入 `orWhereExists`、`orWhereSub`、`unionAll` 之外的新查询 API
- 不在本次变更中支持子查询 join 条件构建器、lateral join、CTE 或窗口函数
- 不改变 `toSql()` / `toSqlParts()` 的返回结构

## Decisions

### 1. 统一引入“子查询规范化”内部步骤

在 `Builder` 内部增加一个私有规范化流程，将 `Closure | Builder | string` 按方法需求转换成统一结构：

- `sql`
- `bindings`

这样公开 API 仍然可以各自保留语义，但内部不再重复构造子查询和抽取绑定。

选择这个方案，是因为当前 `fromSub`、`joinSub`、`whereSub`、`whereExists` 都在重复“新建子 builder -> 回调填充 -> `toSqlParts()`”的流程。继续按方法复制只会让后续特性更难维护。

备选方案是每个方法各自扩展参数类型并单独处理。这个方案实现更快，但会把绑定传播和 SQL 包装逻辑分散到更多分支里，后续很难保证一致性，因此不采用。

### 2. `selectSub` 同时支持原始字符串与 builder 子查询

`selectSub` 现有签名是 `selectSub(string $column, ?string $alias = null)`。本次保留字符串输入兼容性，同时允许 `Closure` 或 `Builder` 作为子查询来源。对子查询来源，编译结果统一包装成 `({subquery}) AS alias`，并将其 bindings 计入 `select` 槽位。

选择保留字符串兼容，是为了避免破坏现有用户代码；同时 builder 输入能让 `SELECT` 列表里的标量子查询不再依赖手写 SQL。

### 3. `whereIn` / `whereNotIn` 采用数组与子查询双模输入

`whereIn` / `whereNotIn` 继续支持值数组；当第二个参数是 `Closure` 或 `Builder` 时，编译为 `column IN (subquery)` 或 `NOT IN (subquery)`。

之所以优先扩展这两个方法，是因为档案规格里已经把 “Where in subquery” 当成目标能力，但当前基线实现并没有真正覆盖。把它纳入本次范围，能让文档与真实能力重新一致。

### 4. 所有新增 bindings 沿用“组件顺序优先”原则

父查询 bindings 的顺序继续遵循 grammar 编译顺序，而不是“子查询先/后追加”的临时约定。也就是说：

- `selectSub` 绑定进入 `select`
- `fromSub` 绑定进入 `from`
- `joinSub` 绑定进入 `join`
- `where*` 子查询绑定进入 `where`

这个决策能保持 `toSqlParts()['bindings']` 与 SQL 中占位符顺序一致，也与当前 grammar 的设计方向一致。

## Risks / Trade-offs

- [方法签名变宽后分支增加] -> 通过单一规范化辅助方法收敛分支，并用测试锁定每类输入
- [`selectSub` 新增 bindings 可能暴露现有 `select` 槽位未充分使用的问题] -> 为 `toSqlParts()` 和 `toSql()` 增加标量子查询绑定测试，确保插值顺序正确
- [允许直接传入 `Builder` 可能引发复用同一个 builder 实例的可变状态问题] -> 文档和测试按“读取当前 builder 快照”定义行为，不尝试冻结或克隆更深层对象图
- [档案规格与当前基线不一致] -> 在本次 change 中显式修正 `query-builder` spec，避免实现完成后文档仍然误导

## Migration Plan

1. 先更新 OpenSpec proposal/specs/design/tasks，明确支持范围
2. 在 `Builder` 中引入子查询规范化辅助方法，并扩展相关 API 签名
3. 在 `Grammar` 中补充 `IN (subquery)` 与标量子查询绑定编译
4. 增加测试，覆盖 closure 与 builder 两类输入
5. 完成后更新 `tasks.md`，并在合并后把稳定行为折叠回基线 specs

## Open Questions

- `selectSub` 是否需要额外支持直接传入 `Builder` 之外的表达式对象？当前看没有必要，先不扩展
- 是否要在本次同时补 `orWhereExists` / `orWhereNotExists`？当前不纳入范围，避免把“子查询输入统一”混成“where API 全量扩展”
