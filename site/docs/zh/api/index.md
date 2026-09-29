# API 参考

公共渲染与组件入口。各页都会说明哪些是公共 API、哪些标记为 `@internal`；
组件接口见 [Component API](/zh/api/component)。

## 核心类

PurePHP 由几个核心类组成，它们协同工作提供强大的模板系统：

### [Tag 类](/zh/api/tag)
所有 HTML、XML 和 SVG 标签的基础抽象类。为属性、子元素和输出方法提供通用功能。

### [HTML 类](/zh/api/html)
专门为 HTML 元素扩展 Tag 类。包括 HTML 特定功能，如自闭合标签检测和文件保存。

### [SVG 类](/zh/api/svg)
继承 `XML` 用于创建 SVG 图形，并处理 SVG 特定的自闭合风格。它不会自动添加 `xmlns`
属性；独立 SVG 根必须显式声明命名空间。

### [XML 类](/zh/api/xml)
为创建 XML 文档扩展 Tag 类。非常适合配置文件、数据导出和 API 响应。

### [Raw 类](/zh/api/raw)
表示绕过转义的原始 HTML 或 XML 内容。用于包含预格式化内容或模板。

### [Compile API](/zh/api/compile)
`Pure\Compile\Compile`、`Shape`、`Renderer`、`Template` 与 `Slot` 介绍编译渲染与
缓存/产物契约；Slot 参考见 [Props 与 Slot](/zh/guide/props#slot-参考)。

### [Component API](/zh/api/component)
`Pure\Component\component()`、`register()`、`Call` 与契约属性介绍公共组件接口。
`Registry` 标记为 `@internal`，应用代码应使用公共辅助函数。

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

use Pure\Compile\Compile;
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

`Pure\Compile\Shape` 提供 `__invoke(array $data)`、`compile()`、`id()`、
`print(array $data)` 与 `save(string $path, array $data, ?string $header = null)`。
`Pure\Compile\Renderer` 提供 `render(array $data)`、
`save(string $path, array $data, string $header = '')`，以及只读属性
`source` / `id` / `slots`。

### 性能指南

- **每个进程只编译一次 Shape** —— 用 `static $shape ??= Compile::shape(...)` 记忆化（标准 PHP-FPM 下请启用 `Compile::cachePath()`，让请求加载渲染器而不是重建）
- **使用函数** 用于标准 HTML/SVG 标签
- **使用魔术方法** 用于自定义或动态标签
- **仅对可信的预格式化内容使用 Raw 类**；它会绕过转义
- **校验活动输入**，页面包含可信标记时使用 Content Security Policy
- **在生产环境启用 `Compile::cachePath()`**，让已预热的 worker 跳过代码生成

## 类层次结构

```
Tag (抽象)
├── HTML
└── XML
    └── SVG

Markup (接口)
├── Raw
└── Call

Pure\Compile\Compile   (门面：shape、cache、guard)
Pure\Compile\Shape     (无数据树)
Pure\Compile\Renderer  (扁平渲染器)
Pure\Core\Slot         (数据占位符)
Pure\Compile\Template  (模板构建器属性)
```

`Tag::export()`、`Shape::tree()` 与 `Pure\Component\Registry` 都是标记为 `@internal` 的
实现细节；应用契约应使用上面列出的公共方法和辅助函数。

## 下一步

- 浏览各个类文档以获取详细示例
- 阅读[编译渲染](/zh/guide/compiled)与[产物与部署](/zh/guide/artifacts)了解生产路径
- 参见 [SVG 和 XML 支持](/zh/guide/svg-xml) 了解图形和数据处理
