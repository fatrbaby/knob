## Context

当前 `Builder` 的 where 条件以扁平数组形式存储，所有条件最终通过 `implode(' AND ', ...)` 拼接。无法表达 `(A OR B) AND C` 这种嵌套结构。

`whereSub()` 和 `whereExists()` 已证明闭包 + 子查询模式的可行性，但它们是专用子查询，不是通用分组。

## Goals / Non-Goals

**Goals:**

- 支持通过闭包对任意 where 条件分组
- 分组内支持 AND/OR 混合
- 不破坏现有 API 兼容性

**Non-Goals:**

- 不支持嵌套超过 2 层（简单起见，复杂嵌套后续扩展）
- 不改变 bindings 的合并顺序

## Decisions

### 1. 分组条件存储结构

```php
// type: 'group' 表示嵌套分组
[
    'type' => 'group',
    'wheres' => [...],   // 内联条件数组
    'boolean' => 'AND',  // 与外层条件的连接方式
]
```

**Why:** 复用现有的 `compileWhereGroup` 编译逻辑，不需要新增编译类型。现有 `whereSub` 的 `sub` 类型已经演示了子查询的存储模式。

### 2. where 方法重载

```php
public function where(string|Closure $column, ...): Builder
```

- 如果 `$column` 是 `Closure`，则进入分组逻辑
- 否则走现有逻辑（保持兼容）

**Why:** API 最简洁，不需新增方法名。Laravel 风格。

### 3. 分组编译

```php
// Grammar.php
protected function compileWhereGroup(array $where): string
{
    $inner = $this->compileWheres($where['wheres']);
    return "({$inner})";
}
```

`compileWheres` 内部已经按 AND 连接分组内的条件，OR 条件在各自的 `boolean` 字段处理。

**Why:** 复用现有 `compileWheres` 的 AND 拼接逻辑，保持一致。

## Risks / Trade-offs

- **绑定顺序**: 分组内 bindings 需要在编译时正确合并到外层 bindings 栈。`compileWheres` 本身处理了递归场景，只要 `compileWhereGroup` 调用 `compileWheres` 时后者能正确积累 bindings 即可。
- **深度嵌套**: 当前实现支持任意深度嵌套（`compileWhere` 递归调用本身），但实际使用中过深可能难维护。
