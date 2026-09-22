# 工具函数

**前置**：[基本概念](/zh/guide/concepts)；**本页**：`clx()`、`sty()` 与 `renderHTML()` / `renderXML()`。

PurePHP 提供了一些实用的工具函数来简化开发：`clx()` 和 `sty()` 在设置元素属性时自动使用，
`renderHTML()` / `renderXML()` 则为标签树或组件调用的渲染结果拼接文档声明。

::: tip 工具函数与编译渲染
`clx()` 和 `sty()` 不受编译渲染影响：在 Shape 中构建静态属性时照常使用，动态值则通过 `Slot::value()` / `Slot::raw()` 绑定——参见[编译渲染](/zh/guide/compiled)。下面的多数示例使用标签 API，它对代码片段和调试依然有效。
:::

## clx 函数

`clx` 函数用于合并类名，支持字符串、数组和条件类名。

### 基本用法

```php
<?php

use function Pure\Utils\clx;

// 合并多个字符串类名
$classes = clx('btn', 'btn-primary', 'large');
echo $classes; // 输出: btn btn-primary large
```

### 条件类名

```php
<?php

use function Pure\Utils\clx;

$isActive = true;
$isDisabled = false;

$classes = clx(
    'btn',
    $isActive ? 'active' : null,
    $isDisabled ? 'disabled' : null
);
echo $classes; // 输出: btn active
```

### 数组支持

```php
<?php

use function Pure\Utils\clx;

$classes = clx(
    'btn',
    [
        'btn-primary',
        'active' => true,
        'disabled' => false,
        'large' => null
    ]
);
echo $classes; // 输出: btn btn-primary active
```

### 在 class() 方法中的内置使用

`class()` 方法内置了 `clx` 函数，可以直接传递多个参数：

```php
<?php

use function Pure\HTML\div;

$isActive = true;
$size = 'large';

div('Content')
    ->class('btn', 'btn-primary', $isActive ? 'active' : null, $size)
    ->print();
```

属性里直接传多个参数即可；只有需要单独拿到合并后的字符串时，才直接调用 `clx()`。

## sty 函数

`sty` 函数用于将样式数组转换为 CSS 字符串。

### 基本用法

```php
<?php

use function Pure\Utils\sty;

$styles = sty([
    'background-color' => 'red',
    'height' => '36px',
    'border' => '1px solid #fff'
]);
echo $styles; // 输出: background-color: red; height: 36px; border: 1px solid #fff;
```

### 条件样式

```php
<?php

use function Pure\Utils\sty;

$isVisible = true;
$color = 'blue';

$styles = sty([
    'color' => $color,
    'display' => $isVisible ? 'block' : 'none',
    'opacity' => $isVisible ? 1 : 0,
    'margin' => null,  // 会被忽略
    'padding' => false // 会被忽略
]);
echo $styles; // 输出: color: blue; display: block; opacity: 1;
```

### 在 style() 方法中的内置使用

`style()` 方法内置了 `sty` 函数，可以直接传递数组：

```php
<?php

use function Pure\HTML\div;

div('Content')
    ->style([
        'background-color' => '#f0f0f0',
        'padding' => '20px',
        'border-radius' => '8px',
        'margin' => '10px 0'
    ])
    ->print();
```

直接传数组即可；只有需要单独拿到合并后的样式字符串时，才直接调用 `sty()`。

## renderHTML 与 renderXML

`renderHTML()` 和 `renderXML()` 渲染标签树或组件调用，并拼接文件头，返回完整文档：

```php
<?php

use Pure\Core\XML;

use function Pure\Component\component;
use function Pure\HTML\{body, h1, html};
use function Pure\Utils\{renderHTML, renderXML};

echo renderHTML(html(body(h1('Hello'))));
// <!DOCTYPE html><html><body><h1>Hello</h1></body></html>

echo renderXML(XML::customers(XML::customer(XML::name('Charter Group'))->id('55000')));
// <?xml version="1.0"?><customers><customer id="55000"><name>Charter Group</name></customer></customers>

echo renderHTML(component('Cover')); // 组件调用同样使用该文件头
```

文件头由函数决定：无论节点是什么，`renderHTML()` 始终拼接 `<!DOCTYPE html>`，
`renderXML()` 拼接 XML 声明。组件调用不暴露自身树，文件头只能来自函数名。
只需要标记本身（例如嵌入页面的片段）时用 `->render()`。

## raw 标记

可信标记使用 `Pure\Core\Raw::of()` 包装；标签 API 会按原样输出。详见 [Raw API](/zh/api/raw)。

## 实际应用示例

### 动态按钮组件

静态配置决定 renderer 的键；标签文本等每次请求的值则是 Slot：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\button;
use function Pure\Utils\sty;

function ActionButton(
    string $text,
    string $variant = 'primary',
    string $size = 'medium',
    bool $loading = false,
    ?string $style = null
): string {
    static $renders = [];

    $render = $renders["{$variant}|{$size}|" . (int) $loading] ??= Compile::shape(
        button(Slot::value('text'))
            ->class('btn', "btn-{$variant}", "btn-{$size}", $loading ? 'loading' : null)
            ->style(Slot::value('style'))
            ->disabled(Slot::value('disabled'))
    );

    return $render([
        'text' => $text,
        'style' => $style,
        'disabled' => null,
    ]);
}

// 仅为渲染时的值；为 null 的属性会被省略。
$style = sty(['opacity' => 1, 'cursor' => 'pointer']);
echo ActionButton('Submit', 'success', 'large', false, $style);
```

### 响应式卡片组件

卡片接受已渲染的 HTML 子内容，因此其内容使用 `Slot::raw()` 绑定：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Raw;
use Pure\Core\Slot;

use function Pure\HTML\{div, h3, p};

function Card(
    string $title,
    iterable|string $content,
    string $theme = 'light',
    bool $featured = false
): string {
    static $renders = [];

    $render = $renders["{$theme}|" . (int) $featured] ??= Compile::shape(
        div(
            h3(Slot::value('title'))->class('card-title'),
            p(Slot::raw('content'))->class('card-content')
        )
        ->class('card', "card-{$theme}", $featured ? 'card-featured' : null)
        ->style([
            'border-width' => $featured ? '2px' : '1px',
            'box-shadow' => $featured ? '0 4px 12px rgba(0,0,0,0.15)' : '0 2px 4px rgba(0,0,0,0.1)',
            'background-color' => $theme === 'dark' ? '#333' : '#fff',
            'color' => $theme === 'dark' ? '#fff' : '#333'
        ])
    );

    return $render(['title' => $title, 'content' => $content]);
}

// `content` 是可信 HTML，将原样输出。
echo Card('Featured Card', Raw::of('<strong>This is the content</strong> of a featured card'), 'dark', true);
```

## 下一步

- [基本用法](/zh/guide/basic-usage) - 学习基础语法和用法
- [组件](/zh/guide/components) - 学习如何创建和使用组件
- [TailwindCSS 集成](/zh/guide/tailwindcss) - 了解如何与 TailwindCSS 配合使用
