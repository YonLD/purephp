# 什么是 PurePHP?

**前置**：无；**本页**：两条渲染路径总览与推荐学习顺序。

PurePHP 是一个受 ReactJS 函数式组件启发的 PHP 模板引擎。你用看起来像 HTML 的 PHP 对象树来描述 UI，PurePHP 将它转换为 HTML 字符串——一切都是 100% 原生 PHP，无需学习任何模板语法。

PurePHP 有两条渲染路径：

| 路径 | 你写什么 | 何时使用 |
| --- | --- | --- |
| **编译渲染** | 组件不含数据的*模板*（一棵 Shape），带 `Slot` 占位符，每个 worker 进程编译一次（或从渲染器缓存加载），每个请求用普通数据渲染 | 生产环境中的页面与组件 |
| **即时渲染** | 包含真实值的标签树，用 `render()` / `print()` 当场渲染 | 代码片段、原型、CLI 工具与调试 |

**学习顺序**：[快速开始](/zh/guide/getting-started)带你跑通第一个组件；
[基本概念](/zh/guide/concepts)与[Props 与 Slot](/zh/guide/props)给出底层词汇——组件包装
一棵含 `Slot` 占位符的无数据 *Shape*；[编译渲染](/zh/guide/compiled)与
[产物与部署](/zh/guide/artifacts)讲这份模板如何编译与交付。

## 为什么选择 PurePHP？

在传统的 PHP 开发中，视图层往往需要混合 HTML、PHP 代码和其他模板语法，这会让开发者感到困惑。PurePHP 通过以下方式解决这些问题：

- **纯 PHP 实现**：所有代码都是 100% 原生 PHP，无需学习新的模板语法
- **组件化开发**：用可复用组件代替重复的 HTML
- **类 HTML 语法**：标签辅助函数与方法链式调用看起来非常接近 HTML
- **编译渲染**：静态标记在编译期只转义一次，因此渲染开销仅比字符串拼接略高

## 编译渲染

组件的模板是一棵不含数据的树：每个 worker 进程编译一次（或从渲染器缓存加载），每个请求用普通数据渲染：

```php [components/Card.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Card(...),
    factory: static fn () => div(
        h2(Slot::value('title')),
        p(Slot::value('content'))
    )->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);

echo Card()->title('Title')->content('Content');
```

`register()` 只惰性保存工厂；产物较新的请求加载已编译的代码，而不是重建模板。标准 PHP-FPM
下每个请求都是全新的，请启用 `Compile::cachePath()`，或用 `vendor/bin/pure compile`
预编译。组合方式见[组件](/zh/guide/components)，缓存见
[编译渲染](/zh/guide/compiled)，产物与生产部署见[产物与部署](/zh/guide/artifacts)。

## 即时渲染

对于代码片段与调试，可以用真实值构建标签树并直接打印：

```php
<?php

use function Pure\HTML\{div, h1, p};

div(
    h1('Welcome to PurePHP'),
    p('A PHP template engine')
)->class('container')->print();
```

## 优势

1. **简单易用**：API 设计简单直观，学习曲线平缓
2. **快速**：编译后的 Shape 渲染速度与编译型模板引擎持平
3. **类型安全**：完整支持 PHP 的类型系统，提供更好的开发体验
4. **轻量级**：核心库体积小巧，没有多余的依赖

## 下一步

- [快速开始](/zh/guide/getting-started) - 安装并跑通第一个组件
- [基本用法](/zh/guide/basic-usage) - 学习代码片段所用的标签 API
- [基本概念](/zh/guide/concepts) - 理解 Tag、Shape、Slot 与组件
- [Props 与 Slot](/zh/guide/props) - Slot 类型与数据绑定参考
- [组件](/zh/guide/components) - Component 是 Shape 的包装与高级用法
- [编译渲染](/zh/guide/compiled) - 组件模板如何编译
- [产物与部署](/zh/guide/artifacts) - `pure compile` 产物与生产部署
