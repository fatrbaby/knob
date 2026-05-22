## Why

当前 Knob 已支持部分子查询能力，但入口并不一致：有的方法只接受 `Closure`，有的方法只接受原始字符串，还有的方法完全不支持子查询输入。继续扩展查询能力前，需要先统一子查询的构建模型，否则 API 会越来越碎片化，测试和绑定传播也会持续分散。

## What Changes

- 统一子查询相关 API 的输入模型，使现有子查询入口可接受 `Closure` 或 `Builder` 实例，而不是各自定义不同参数形态
- 扩展 `selectSub`，使其支持以查询构建器形式定义标量子查询，而不仅是传入原始 SQL 字符串
- 扩展 `whereIn` / `whereNotIn` / `whereSub` / `whereExists` / `whereNotExists`，支持直接复用已有子查询构建器
- 保持 `fromSub`、`joinSub`、`union` 现有绑定传播语义一致，新增子查询入口也必须维持正确的绑定顺序
- 为子查询编译和绑定合并补齐行为测试，覆盖嵌套子查询与复用 builder 的场景

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `query-builder`: 扩展现有查询构建器的子查询输入能力，统一标量子查询、`IN` 子查询和 `EXISTS` 子查询的构建方式

## Impact

- `src/Builder.php` — 调整子查询相关方法签名，新增内部子查询规范化逻辑
- `src/Grammars/Grammar.php` — 复用并扩展子查询 SQL/绑定编译路径
- `tests/Unit/BuilderTest.php` — 增加 builder/closure 子查询输入与绑定顺序测试
- `openspec/specs/query-builder/spec.md` — 更新查询构建器的子查询行为说明
