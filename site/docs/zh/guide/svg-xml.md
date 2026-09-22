# SVG 和 XML 支持

**前置**：[基本用法](/zh/guide/basic-usage)；**本页**：SVG 图形与 XML 文档的构建和编译导出。

PurePHP 为创建 SVG 图形和 XML 文档提供全面支持，使用与 HTML 相同的优雅语法。

::: tip SVG 与 XML 的两条路径
HTML、SVG 和 XML 标签实例都继承自 `Tag`，因此它们中的任何一个都可以包装进 `Compile::shape()` 并用数据渲染——参见[编译渲染](/zh/guide/compiled)。下面的 SVG 部分属于标签 API 参考，使用 `render()` / `print()` 即时渲染；XML 部分使用编译路径。
:::

## SVG 支持

### 基本 SVG 创建

使用 `Pure\SVG` 函数创建 SVG 图形，自定义标签则使用魔术静态方法：

```php
<?php

use function Pure\SVG\{svg, circle, rect, path};

$graphic = svg(
    circle()
        ->cx('50')
        ->cy('50')
        ->r('40')
        ->fill('red'),
    rect()
        ->x('10')
        ->y('10')
        ->width('80')
        ->height('80')
        ->fill('blue')
)->width('100')->height('100');

echo $graphic; // 输出 SVG 标记
```

### 函数 vs 魔术静态方法

**使用函数当：**
- 标签属于预定义的 HTML/SVG 标签
- 需要处理动态值（子节点与属性）

**使用魔术静态方法当：**
- 创建自定义或非标准标签
- 使用动态标签名

两者构建的都是同一个 `Tag` 对象；函数只是常用标签名的薄而明确的包装。
自定义标签使用魔术静态接口：

```php
<?php

use Pure\Core\SVG;

// 非常适合非标准或自定义 SVG 元素
$customElement = SVG::customTag(SVG::innerElement('content'))
    ->customAttribute('value');

// 动态标签名同样可用
$tag = 'myCustomSvgElement';
$webComponent = SVG::{$tag}()
    ->data_id('unique')
    ->class('custom-svg');
```

### 复杂 SVG 示例

#### 创建图标

```php
<?php

use Pure\Core\SVG;

use function Pure\SVG\{svg, path, g};

function ChevronIcon($direction = 'right'): SVG
{
    $rotation = match($direction) {
        'up' => 'rotate(-90 12 12)',
        'down' => 'rotate(90 12 12)',
        'left' => 'rotate(180 12 12)',
        default => ''
    };

    return svg(
        path('M9 18l6-6-6-6')
            ->stroke('currentColor')
            ->stroke_width('2')
            ->fill('none')
            ->stroke_linecap('round')
            ->stroke_linejoin('round')
            ->transform($rotation)
    )->width('24')->height('24')->viewBox('0 0 24 24');
}

echo ChevronIcon('down')->class('icon');
```

#### 动画 SVG

```php
<?php

use Pure\Core\SVG;

$animatedCircle = SVG::svg(
    SVG::circle()
        ->cx('50')
        ->cy('50')
        ->r('40')
        ->fill('red'),
    SVG::animate()
        ->attributeName('r')
        ->values('40;45;40')
        ->dur('2s')
        ->repeatCount('indefinite')
)->width('100')->height('100');
```

## XML 支持

XML 标签同样继承自 `Tag`，因此文档以编译 Shape 构建：树及其 Slot 每个进程只创建一次，每次导出时绑定数据并保存或打印。

### 编译 XML 文档

`Address()` 渲染一条记录；`city` 是可选的，仅在数据提供时才出现：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;
use Pure\Core\XML;

function Address(array $address): string
{
    static $render;
    $render ??= Compile::shape(
        XML::address(
            XML::street(Slot::value('street')),
            Slot::if('city', XML::city(Slot::value('city'))),
            XML::state(Slot::value('state')),
            XML::zip(Slot::value('zip'))
        )
    );

    return $render($address);
}

function Customers(array $addresses): string
{
    static $render;
    $render ??= Compile::shape(
        XML::customers(
            XML::customer(
                XML::name('Charter Group'),
                Slot::raw('addresses')
            )->id('55000')
        )
    );

    $html = '';

    foreach ($addresses as $address) {
        $html .= Address($address);
    }

    return $render(['addresses' => $html]);
}

echo Customers([
    ['street' => '100 Main', 'city' => 'Framingham', 'state' => 'MA', 'zip' => '01701'],
    ['street' => '720 Prospect', 'city' => 'Framingham', 'state' => 'MA', 'zip' => '01701'],
    ['street' => '120 Ridge', 'state' => 'MA', 'zip' => '01760'],
]);
```

`Slot::if()` 对没有 `city` 的记录跳过该元素——缺失的键为 false，且绝不抛出异常。要把文档写入文件，用 `Shape::save()` 即可：它默认补上根标签的文档声明，也可把自定义声明作为第三个参数传入。

### 数据驱动的元素

标签名在构建时固定，因此动态的键和值成为 Slot——这里是设置列表上的 `key` 属性和文本内容：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;
use Pure\Core\XML;

$setting = Compile::shape(
    XML::setting(Slot::value('value'))->key(Slot::value('key'))
);

$config = Compile::shape(
    XML::configuration(Slot::each('settings', $setting))
);

$config->print(['settings' => [
    ['key' => 'host', 'value' => 'localhost'],
    ['key' => 'port', 'value' => '3306'],
    ['key' => 'debug', 'value' => 'true'],
]]);
```

当结构本身必须随数据变化时，请使用 `Slot::if()` 或在数据层分派；Shape 的标签集合无法变化。

## 重要：字符串内容会被转义

⚠️ **安全提示**：字符串内容一律会被转义，因此形似 XML/SVG 的文本是安全的，并且会原样显示：

```php
<?php

use Pure\Core\Raw;
use Pure\Core\XML;

// ✅ 字符串中的 XML 标签会被转义，不会被解析
XML::root('<item>This stays visible</item>')->print();
// 输出: <root>&lt;item&gt;This stays visible&lt;/item&gt;</root>

// ✅ 使用 Raw::of 输出 XML 内容
XML::root(Raw::of('<item>This is preserved</item>'))->print();
// 输出: <root><item>This is preserved</item></root>
```

**何时使用 Raw::of()：**
- 包含 CDATA 部分
- 嵌入外部 XML/SVG 内容
- 处理预格式化的标记
- 包含复杂的嵌套结构

两条渲染路径行为一致；绑定的数据由 `Slot::value()` 转义，当数据必须保留标记时，`Slot::raw()` 是 `Raw::of()` 的等价原样输出。

## 最佳实践

1. **对预定义的 HTML/SVG 标签使用函数**——它们提供性能和可读性的最佳平衡
2. **对自定义标签使用魔术方法**——当你需要动态创建标签时
3. **对可信内容使用 Raw::of()**——当你需要保留标记结构时
4. **根据需要组合方法**——你可以根据具体用例混合搭配

## 下一步

- [API 参考](/zh/api/)——完整的 API 文档
- [组件](/zh/guide/components)——学习创建可重用组件
- [工具函数](/zh/guide/utils)——探索辅助函数
