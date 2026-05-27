## Why

当前 `Builder` 的 where 条件全部通过 AND 连接，无法实现条件分组。例如 `(type = 'A' OR type = 'B') AND status = 'active'` 这类逻辑在复杂查询中常常需要，但现有 API 不支持。

## What Changes

- 新增 `where(Closure $callback)` 方法，支持闭包内联分组条件
- 所有现有 where 方法（`whereIn`, `whereBetween`, `whereNull`, `whereExists` 等）均可传入闭包实现分组
- 分组条件内部支持 AND/OR 混合使用
- Grammar 层新增 `group` 类型处理嵌套条件编译

## Capabilities

### New Capabilities

- `nested-where-groups`: 支持通过闭包将多个 where 条件分组，支持分组内 AND/OR 混合逻辑

## Impact

- `src/Builder.php` — 新增 `where(Closure)` 方法，所有现有 where 方法的重载变体
- `src/Grammars/Grammar.php` — 新增 `compileWhereGroup` 方法，`compileWhere` 新增 `group` 类型处理
- 测试覆盖分组场景
