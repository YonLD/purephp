# 基础用法

**前置**：[快速开始](/zh/guide/getting-started)；**本页**：标签 API——创建元素、设置属性与即时渲染。

::: tip 标签 API 与编译渲染
本页介绍用于代码片段、原型和调试的标签 API；以此方式构建的树通过 `render()` / `print()`
即时渲染。生产页面应改为编译 Shape——参见[编译渲染](/zh/guide/compiled)。
:::

## 基本语法

### 1. 创建 HTML 元素

PurePHP 提供两种创建 HTML 元素的方式：

#### 函数方式（推荐用于预定义标签）

PurePHP 使用函数调用的方式来创建 HTML 元素：

```php
<?php

use function Pure\HTML\div;
use function Pure\HTML\h1;
use function Pure\HTML\p;

// 创建简单的 div 元素
div('Hello World')->print();

// 创建嵌套的元素
div(
    h1('Title'),
    p('Paragraph content')
)->class('container')->print();
```

#### 魔术静态方法（适合自定义标签）

```php
<?php

use Pure\Core\HTML;

// 使用魔术方法创建自定义 HTML 元素
// （方法名就是标签名：这里会输出 <customTag class="custom">）
HTML::customTag('Custom content')->class('custom')->print();

// 非常适合 Web 组件或非标准标签
HTML::myComponent(
    HTML::header('Component Header'),
    HTML::content('Component Body')
)->data_component('my-component')->print();
```

自定义标签接受与函数相同的子节点参数，因此动态标签名也可用：

```php
<?php

use Pure\Core\HTML;

$tag = 'my-element';
HTML::{$tag}('Content')->class('dynamic')->print();
```

### 2. 设置属性

使用链式调用设置元素属性：

```php
<?php

use function Pure\HTML\div;

div('Content')
    ->class('container')
    ->style('background: #fff;')
    ->data_key('primary')
    ->id('main')
    ->print();
```

## 选择正确的方式

### 何时使用每种方法

#### 使用函数（推荐大多数情况）
- **最适合**：标准 HTML 标签，日常开发
- **优点**：语法简洁，性能良好，可读性优秀
- **示例**：`div()`、`p()`、`span()` 等

#### 使用魔术静态方法
- **最适合**：自定义标签、Web 组件、动态标签名
- **优点**：适用于任何标签名，语法优雅
- **示例**：`HTML::customElement()`、`HTML::webComponent()`

```php
<?php

use function Pure\HTML\div;
use Pure\Core\HTML;

// 函数方式 - 标准标签
$standard = div('Standard content')->class('container');

// 魔术方法 - 自定义标签
$custom = HTML::myCustomTag('Custom content')->data_component('special');
```

## 重要用法说明

### 1. 字符串内容 vs 原始内容

字符串子节点一律转义，因此对用户输入是安全的，也不会丢数据：`2<3`、`a<b`
这类比较文本会原样保留。形似标签的字符串会作为文本显示，而不会被解析：

```php
<?php

use Pure\Core\Raw;

use function Pure\HTML\div;

// ✅ 字符串内容被转义，不会被解析
div('<p>This is shown as text</p>')->print();
// 输出: <div>&lt;p&gt;This is shown as text&lt;/p&gt;</div>

// ✅ 使用 Raw::of 输出可信标记
div(Raw::of('<p>This is preserved</p>'))->print();
// 输出: <div><p>This is preserved</p></div>
```

**为什么这很重要：**
- **安全性**：转义消除了用户输入中的 XSS
- **不丢数据**：只是看起来像标记的文本会被完整保留
- **明确性**：输出标记必须显式使用 Raw::of()

**何时使用 Raw::of()：**
- 包含预格式化的 HTML/XML 内容
- 嵌入模板或外部内容
- 处理可信的 HTML/XML 字符串
- 包含 JavaScript 或 CSS 代码块

### 2. className 别名

由于 `class` 是 PHP 的关键字，PurePHP 提供了 `className` 作为别名：

```php
<?php

use function Pure\HTML\div;

// 两种写法都可以
div('Content')->class('container')->print();
div('Content')->className('container')->print();
```

### 3. 内置工具函数

`class` 方法内置了 `clx` 函数，`style` 方法内置了 `sty` 函数，可以处理数组和条件参数：

```php
<?php

use function Pure\HTML\div;

$isActive = true;
$isLarge = false;

div('Content')
    ->class('btn', $isActive ? 'active' : null, $isLarge ? 'large' : null)
    ->style(['color' => 'red', 'font-size' => '16px'])
    ->print();
```

需要单独合并类名/样式字符串时，再用[工具函数](/zh/guide/utils)里的 `clx()` / `sty()`。

### 4. 属性命名规则

由于 `-` 在 PHP 中有特殊含义，类似 `data-id` 这种属性需要改成 `data_id`：

```php
<?php

use function Pure\HTML\div;

div('Content')
    ->data_id('123')           // 对应 data-id="123"
    ->data_type('card')        // 对应 data-type="card"
    ->aria_label('Button')     // 对应 aria-label="Button"
    ->print();
```

### 5. 添加子元素

可以通过参数传递添加多个子元素：

```php
<?php

use function Pure\HTML\div;
use function Pure\HTML\p;

div(
    p('First paragraph'),
    p('Second paragraph'),
    p('Third paragraph')
)->class('content')->print();
```

## 常用 HTML 标签

PurePHP 支持所有常用的 HTML 标签：

```php
<?php

use function Pure\HTML\{
    div, span, p, h1, h2, h3, h4, h5, h6,
    a, img, ul, ol, li, table, tr, td, th,
    form, input, button, textarea, select, option
};

// 创建链接
a('Click here')->href('https://example.com')->print();

// 创建图片
img()->src('image.jpg')->alt('Image description')->print();

// 创建列表
ul(
    li('Item 1'),
    li('Item 2'),
    li('Item 3')
)->class('list')->print();

// 创建表单
form(
    input()->type('text')->name('username'),
    input()->type('password')->name('password'),
    button('Submit')->type('submit')
)->method('POST')->action('/login')->print();
```

## SVG 支持

PurePHP 内置支持 SVG 标签：

```php
<?php

use function Pure\SVG\{svg, circle, rect, path};

// 创建简单的圆形
svg(
    circle()
        ->cx('50')
        ->cy('50')
        ->r('40')
        ->stroke('black')
        ->stroke_width('3')
        ->fill('red')
)->width('100')->height('100')->print();

// 创建矩形
svg(
    rect()
        ->x('10')
        ->y('10')
        ->width('80')
        ->height('80')
        ->fill('blue')
)->width('100')->height('100')->print();
```

## 条件渲染

使用 PHP 的条件语句进行条件渲染：

```php
<?php

use function Pure\HTML\{div, p};

$isLoggedIn = true;

div(
    $isLoggedIn ? p('Welcome back!') : p('Please log in')
)->class('message')->print();
```

在编译渲染中，条件会成为一个 `Slot::if()` 占位符，各分支则是 Shape。诸如 `Slot::value()` 这类 Slot 用于代表在渲染时绑定的值：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, p};

function Message(bool $isLoggedIn): string
{
    static $render;
    $render ??= Compile::shape(
        div(
            Slot::if(
                'isLoggedIn',
                p('Welcome back!'),
                p('Please log in')
            )
        )->class('message')
    );

    return $render(['isLoggedIn' => $isLoggedIn]);
}

echo Message(true);
```

`Slot::if()` 读取当前数据作用域，缺失的键视为 false，renderer 通过 `static` 记忆化，因此每个进程只构建一次。

## 循环渲染

使用 PHP 的循环语句渲染列表：

```php
<?php

use function Pure\HTML\{ul, li};

$items = ['Apple', 'Banana', 'Orange'];

ul(
    ...array_map(fn($item) => li($item), $items)
)->class('fruits')->print();
```

在编译渲染中，列表是 `Slot::each()` Slot：条目 Shape 会为所绑定可迭代对象的每个元素渲染，`Slot::value()` 标记要绑定的值：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{ul, li};

function Fruits(array $items): string
{
    static $render;
    $render ??= Compile::shape(
        ul(Slot::each('items', li(Slot::value('name'))))->class('fruits')
    );

    return $render(['items' => $items]);
}

echo Fruits([
    ['name' => 'Apple'],
    ['name' => 'Banana'],
    ['name' => 'Orange'],
]);
```

每个元素都是一个数组，提供条目 Shape 所使用的 Slot 名；请求只向已编译好的 renderer 绑定数据。

## 样式处理

### 1. 内联样式

```php
<?php

use function Pure\HTML\div;

div('Content')
    ->style('
        background: #f0f0f0;
        padding: 20px;
        border-radius: 8px;
    ')
    ->print();
```

### 2. 类名处理

一次 `class()` 调用会把所有参数拼接起来，而第二次 `class()` 调用会覆盖第一次的值，
所以请把类名都放进同一次调用：

```php
<?php

use function Pure\HTML\div;

$isActive = true;

div('Content')
    ->class('container', $isActive ? 'active' : 'inactive')
    ->print();
```

## 下一步

- [基本概念](/zh/guide/concepts) - Tag、Shape、Slot 与组件
- [Props 与 Slot](/zh/guide/props) - Slot 类型与数据绑定参考
- [组件](/zh/guide/components) - Component 是 Shape 的包装与高级用法
- [编译渲染](/zh/guide/compiled) - 组件模板如何编译
- [SVG 和 XML 支持](/zh/guide/svg-xml) - 了解 SVG 图形和 XML 文档
