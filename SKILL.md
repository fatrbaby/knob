---
name: php-project-guide
description: PHP 8.x guardrails, patterns, and best practices for this project. Use when working with .php files, composer.json, or refactoring code.
compatibility: Requires PHP 8.1+, Composer
---

# PHP 项目开发规范

## 核心编码标准
- 目标版本：严格使用 PHP 8.1+ 特性（如 Enums、Readonly Properties、Constructor Property Promotion）。
- 类型声明：所有函数参数与返回值（含 `void`）必须显式声明类型。
- 错误处理：严禁使用 `@` 错误抑制符，统一抛出具体业务异常。

## 工具链与检查
- 静态分析：使用 PHPStan（Level 8）。
- 代码风格：遵循 PSR-12 / PER-CS 规范，通过 pint 校验。
- 运行测试：提交前执行 `composer test`（基于 PHPUnit 或 Pest）。
