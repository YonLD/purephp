---
title: 什么是 PurePHP？
description: 从即时标签树到编译组件与生产产物的 PurePHP 渐进式入门路径。
---

# 什么是 PurePHP？

如果你是第一次接触 PurePHP，请从这里开始。你不需要其它模板语言基础；准备好
PHP 8.1 或更新版本与 Composer 即可。页面按顺序组织，每一步都建立在上一步的
概念之上。

PurePHP 是一个受 ReactJS 函数式组件启发的 PHP 模板引擎。你用看起来像 HTML 的
原生 PHP 对象描述 UI，PurePHP 再把它渲染成 HTML 字符串；无需学习另一套模板语法。

## 选择学习路径

| 步骤 | 页面 | 你会学到 |
| --- | --- | --- |
| 1 | [快速开始](/zh/guide/getting-started) | 安装包并渲染第一棵标签树。 |
| 2 | [基本用法](/zh/guide/basic-usage) | 组合类 HTML 元素、属性、children 与输出。 |
| 3 | [核心概念](/zh/guide/concepts) | 认识 Tag、Shape、Slot 与 Renderer。 |
| 4 | [Props 与 Slot](/zh/guide/props) | 绑定文本、raw 标记、child 作用域、列表与条件。 |
| 5 | [组件](/zh/guide/components) | 用调用函数和类型化 props 包装 Shape。 |
| 6 | [编译渲染](/zh/guide/compiled) | 每个 worker 编译一次，再用请求数据渲染。 |
| 7 | [产物与部署](/zh/guide/artifacts) | 安全交付 `*.pure.php` 与 `*.plain.php`。 |

完成核心路径后，按应用需要选择集成与示例：[事件](/zh/guide/events)、
[SVG 与 XML](/zh/guide/svg-xml)、[工具函数](/zh/guide/utils)、[HTMX](/zh/guide/htmx)、
[TailwindCSS](/zh/guide/tailwindcss)，以及可直接运行的[示例](/zh/guide/examples)。

## 两条渲染路径

PurePHP 有意区分两条路径：

| 路径 | 你写什么 | 适合 |
| --- | --- | --- |
| **即时渲染** | 包含真实值的标签树，用 `render()` 或 `print()` 输出。 | 片段、原型、CLI 工具与调试。 |
| **编译渲染** | 含 `Slot` 占位符的不含数据 Shape，每个 worker 编译一次，或加载已生成的最新产物。 | 可复用组件与生产页面。 |

Shape 不是第二套模板语言，而是把动态值替换为占位符的 PHP 标签树。混用两条路径
前请先阅读[核心概念](/zh/guide/concepts)。

## Hello, PurePHP

最小可用程序仍然是普通 PHP：

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{div, h1, p};

div(
    h1('Hello PurePHP'),
    p('This renders immediately.')
)->class('container')->print();
```

可复用组件由调用函数返回 `Pure\Component\Call`，再由惰性 factory 提供 Shape：

```php
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Card(...), static fn () => div(
    h2(Slot::value('title')),
    p(Slot::value('content'))
)->class('card'));

echo Card()->title('Title')->content('Content');
```

可以再加 `prepare()` 提供类型化 prop 契约；`pure compile` 能把单元预编译为
`*.pure.php` 产物。children、列表 Slot、页面与契约检查见[组件](/zh/guide/components)。

## 为什么选择 PurePHP？

- **原生 PHP：** 视图层就是 PHP，不是新的模板语言。
- **类 HTML 组合：** 辅助函数与链式 setter 读起来像标签。
- **可复用单元：** 组件 = Shape + 类型化调用契约。
- **可重复渲染：** 编译 Shape 把静态工作移出请求路径，同时保留一致的转义与输出语义。

## 遇到问题时

请按症状阅读[故障排查](/zh/guide/troubleshooting)，不要先钻进实现细节。如果要从
其它版本或源码 checkout 迁移，先看[升级与发布](/zh/guide/upgrading)，再有意识地
重建产物。

## 下一步

- [快速开始](/zh/guide/getting-started)——安装、自动加载并运行第一页
- [示例](/zh/guide/examples)——比较 `pure`、`plain`、静态 `/cover`、counter 与 XML 路由
- [组件 API](/zh/api/component)——公共组件入口与 PHPStan 集成
- [编译 API](/zh/api/compile)——Slot、Shape、Renderer、缓存与产物的规范参考
- [故障排查](/zh/guide/troubleshooting)——常见运行时与构建问题的修复
