# Tag 类

`Pure\Core\Tag` 是所有 HTML 和 SVG 标签的基础抽象类。

标签树有两种用途：

- **即时渲染**（片段与调试）：用真实值构建树，再用 `render()` / `print()` 渲染。
- **编译渲染**（生产环境）：用 `Pure\Core\Slot` 占位符构建不含数据的树，通过
  `Pure\Compile\Compile::shape()` 包装，并在渲染时绑定数据。参见[编译渲染](./compile)。

下文的属性方法与遍历方法为两条路径共用。

## 属性方法

### `class(array|bool|int|float|string|Slot|null ...$args): self`

设置元素的 CSS 类名，内置 `clx` 函数处理多个参数。布尔值会被忽略，因此条件参数依然可用（`->class('btn', $isActive ? 'active' : null)`）。空字符串、`null` 与空数组不会产生 `class` 属性。

```php
<?php

use function Pure\HTML\div;

// 单个类名
div('Content')->class('container');

// 多个类名
div('Content')->class('btn', 'btn-primary', 'large');

// 条件类名
$isActive = true;
div('Content')->class('btn', $isActive ? 'active' : null);

// 数组格式
div('Content')->class(['btn', 'btn-primary']);

// 动态类名（编译渲染）
div('Content')->class(\Pure\Core\Slot::value('classList'));
```

### `className(array|bool|int|float|string|Slot|null ...$args): self`

`class()` 方法的别名，因为 `class` 是 PHP 关键字。

```php
<?php

use function Pure\HTML\div;

div('Content')->className('container');
```

### `style(string|array|Slot|null $value): self`

设置元素的内联样式，同时支持字符串和数组格式。

```php
<?php

use function Pure\HTML\div;

// 字符串格式
div('Content')->style('background: #fff; padding: 20px;');

// 数组格式（内置 sty 函数）
div('Content')->style([
    'background-color' => '#fff',
    'padding' => '20px',
    'border-radius' => '8px'
]);
```

## 设置方法

### `setAttrs(array $attrs): self`

一次性设置多个属性。

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->setAttrs([
    'id' => 'main',
    'class' => 'container',
    'data-type' => 'card'
]);
```

值必须是标量、`Stringable`、`Slot` 或 `null`；数组会抛出 `InvalidArgumentException`（数组请使用 `class()`/`style()`）。键名会像链式 setter 一样归一化：`className` → `class`，`data_id` → `data-id`。

## 获取方法

### `getTagName(): string`

获取标签名。

```php
<?php

use function Pure\HTML\div;

$element = div('Content');
echo $element->getTagName(); // 输出: div
```

### `getAttrs(): array`

获取所有属性的关联数组。

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container')->id('main');
$attrs = $element->getAttrs();
// 返回: ['class' => 'container', 'id' => 'main']
```

### `getAttr(string $key): string|Slot|null`

获取指定属性的值；属性不存在时返回 `null`。

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container');
echo $element->getAttr('class');   // 输出: container
var_dump($element->getAttr('id')); // NULL
```

### `getChildren(): array`

获取所有子元素。

```php
<?php

use function Pure\HTML\{div, p};

$element = div(p('Paragraph 1'), p('Paragraph 2'));
$children = $element->getChildren();
```

## 自闭合标签方法

### `getSelfClose(): bool`

检查元素是否为自闭合标签。

```php
<?php

use function Pure\HTML\{div, img};

$div = div('Content');
echo $div->getSelfClose(); // 输出: false

$img = img()->src('image.jpg');
echo $img->getSelfClose(); // 输出: true
```

### `setSelfClose(bool $value): self`

设置元素是否为自闭合标签。

```php
<?php

use function Pure\HTML\div;

$element = div()->setSelfClose(true);
```

## 输出方法

### `toJSON(): array`

把元素转换为嵌套的 JSON 兼容数组：`tagName`、`attrs`、`children`。属性放在独立
键下，因此属性名永远不会与结构键冲突。Slot 描述为 `['slot' => 'name']`。

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container');
$json = $element->toJSON();
// 返回: [
//     'tagName' => 'div',
//     'attrs' => ['class' => 'container'],
//     'children' => ['Content'],
// ]
```

### `render(): string`

直接用真实值把标签树及其子节点渲染为 HTML 字符串。渲染时属性值和文本子节点会被转义；
`Pure\Core\Markup` 子节点——`Raw` 与组件调用——按原样输出，并随树延迟渲染。

`render()`（以及 `print()` / `__toString()`）是**片段与调试**出口。生产页面应改为
编译 Shape，这样静态标记只在编译期转义一次——参见[编译渲染](./compile)。

含 `Slot` 占位符的树不能直接渲染：请用 `Pure\Compile\Compile::shape()` 编译，并在渲染
时绑定数据。

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container');
echo $element->render(); // 输出: <div class="container">Content</div>
```

### `__toString(): string`

对元素进行字符串转换，等价于 `render()`。

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container');
echo (string)$element; // 输出: <div class="container">Content</div>
```

### `print(): void`

直接输出元素的 HTML 字符串。

```php
<?php

use function Pure\HTML\div;

div('Content')->class('container')->print();
// 输出: <div class="container">Content</div>
```

### `save(string $path, ?string $header = null): int|false`

将渲染后的树写入文件。省略 `$header` 时会补上该标签类型的文档声明（HTML 为
`<!DOCTYPE html>`，XML 与 SVG 为 XML 声明）；传入 `$header` 可覆盖。返回写入的
字节数，失败时返回 `false`。

```php
<?php

use function Pure\HTML\{div, h1};

div(h1('Report'))->save('report.html');
```

### `isDocumentRoot(): bool`

该标签是否作为完整文档的根，从而在编译出的无依赖视图中前置文档声明。HTML 树只有在根为
`<html>` 时才是文档，XML 树始终是；SVG 树是片段（图标以内联方式使用），其独立文件的
声明仍可通过 `documentHeader()` / `save()` 获得。

## 动态属性方法

Tag 类通过 `__call` 魔术方法支持动态设置任何 HTML 属性：

```php
<?php

use function Pure\HTML\{div, input, img};

// 设置 ID
div('Content')->id('main');

// 设置 data 属性（注意使用下划线）
div('Content')->data_id('123')->data_type('card');

// 设置 ARIA 属性
div('Content')->aria_label('Main content');

// 设置表单属性
input()->type('text')->name('username')->placeholder('Enter username');

// 设置图片属性
img()->src('image.jpg')->alt('Image description')->width('100')->height('100');
```

开启开发守卫（`Compile::guard(true)` / `PURE_COMPILE_GUARD=1`）后，与标准属性名只差
一个字符的方法会警告一次——`->clas(...)`、`->hreff(...)` 会建议 `class` 与 `href`，
而不是静默变成自定义属性。确实要自定义属性时照常书写即可；该警告只是提示，默认关闭。
