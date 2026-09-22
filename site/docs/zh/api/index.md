# API 参考

本节为所有 PurePHP 类及其方法提供全面的文档。

## 核心类

PurePHP 由几个核心类组成，它们协同工作提供强大的模板系统：

### [Tag 类](/zh/api/tag)
所有 HTML 和 SVG 标签的基础抽象类。为属性、子元素和输出方法提供通用功能。

### [HTML 类](/zh/api/html)
专门为 HTML 元素扩展 Tag 类。包括 HTML 特定功能，如自闭合标签检测和文件保存。

### [SVG 类](/zh/api/svg)
为创建 SVG 图形扩展 XML 类。自动处理 SVG 特定的自闭合标签和命名空间。

### [XML 类](/zh/api/xml)
为创建 XML 文档扩展 Tag 类。非常适合配置文件、数据导出和 API 响应。

### [Raw 类](/zh/api/raw)
表示绕过转义的原始 HTML 或 XML 内容。用于包含预格式化内容或模板。

### [Compile API](/zh/api/compile)
`Pure\Compile\Compile`、`Shape` 与 `Renderer` 把带 `Slot` 占位符的无数据 Shape 树编译成扁平 PHP 渲染器。静态标记变成字面量，渲染速度与编译型模板引擎持平，同时保留流式 PHP API。

## 快速参考

### 创建元素

```php
<?php

use Pure\Core\HTML;
use function Pure\HTML\div;
use function Pure\SVG\circle;

// 函数方式（标准标签）
$element1 = div('Content');

// 魔术静态方法（自定义标签）
$element2 = HTML::customTag('Content');
```

### 编译 Shape

```php
<?php

use Pure\Compile\{Compile, Shape};
use Pure\Core\Slot;

use function Pure\HTML\{div, h1};

$shape = Compile::shape(
    div(h1(Slot::value('title')))->class('card')
);

$shape->print(['title' => 'Hello']);
```

### 通用方法

所有基于 Tag 的类都共享这些通用方法：

- `class()` / `className()` - 设置 CSS 类
- `style()` - 设置内联样式
- `id()`, `data_*()`, `aria_*()` - 设置属性
- `getTagName()`, `getAttrs()`, `getChildren()` - 获取信息
- `toJSON()`, `render()`, `print()`, `__toString()` - 输出方法（片段/调试）

`Pure\Compile\Shape` 提供 `__invoke($data)`、`print($data)`、`save($path, $data)` 和 `compile()`；
`Pure\Compile\Renderer` 提供 `render($data)`、`save($path, $data)`，以及只读属性
`source` / `id`。

### 性能指南

- **每个进程只编译一次 Shape** —— 用 `static $shape ??= Compile::shape(...)` 记忆化（标准 PHP-FPM 下请启用 `Compile::cachePath()`，让请求加载渲染器而不是重建）
- **使用函数** 用于标准 HTML/SVG 标签
- **使用魔术方法** 用于自定义或动态标签
- **使用 Raw 类** 用于预格式化内容
- **在生产环境启用 `Compile::cachePath()`**，让已预热的 worker 跳过代码生成

## 类层次结构

```
Tag (抽象)
├── HTML
└── XML
    └── SVG

Raw

Pure\Compile\Compile   (门面：shape、cache、guard)
Pure\Compile\Shape     (无数据树)
Pure\Compile\Renderer  (扁平渲染器)
Pure\Core\Slot         (数据占位符)
```

## 下一步

- 浏览各个类文档以获取详细示例
- 阅读[编译渲染](/zh/guide/compiled)与[产物与部署](/zh/guide/artifacts)了解生产路径
- 参见 [SVG 和 XML 支持](/zh/guide/svg-xml) 了解图形和数据处理
