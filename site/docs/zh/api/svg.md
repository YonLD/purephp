# SVG 类

`Pure\Core\SVG` 继承 `Pure\Core\XML`，用于 SVG 标签。它会为选定的无子节点元素
选择自闭合渲染风格，但不会自动添加 SVG 命名空间。

## 创建 SVG 元素

标准 SVG 标签来自 `Pure\SVG` 函数；其他标签名使用魔术静态接口：

```php
<?php

use function Pure\SVG\{circle, rect, svg};

$circle = circle()->cx('50')->cy('50')->r('40')->fill('red');
$rect = rect()->x('10')->y('10')->width('80')->height('80')->fill('blue');
$svg = svg($circle, $rect)->width('100')->height('100');
```

```php
<?php

use Pure\Core\SVG;

// 任何标签名，包括自定义元素
$custom = SVG::customShape(SVG::innerPath('M10,10 L90,90'));
```

## 命名空间与文档根

SVG 通常作为内联片段使用，因此 `isDocumentRoot()` 返回 `false`，`render()` 只输出元素。
类名不会自动推导 `xmlns` 属性。写独立 SVG 文件时请显式声明命名空间：

```php
<?php

use function Pure\SVG\{path, svg};

$icon = svg(
    path('M3 12l2-2m0 0l7-7 7 7')
)->xmlns('http://www.w3.org/2000/svg')->viewBox('0 0 24 24');

$icon->save('icon.svg');
```

SVG 类的 `save()` 与 `documentHeader()` 仍会提供 XML 声明；需要不同文档头时，请显式传入
header 参数。`renderXML()` 也会前置该声明，因此内联片段请使用 `render()`。命名空间属于
文档契约而不是转义问题，应固定在模板中，不要从不可信数据读取。

## 自闭合标签

SVG 没有 void 元素：`<feTile />` 与 `<feTile></feTile>` 描述同一份文档，因此这里的
自闭合只是渲染风格，而不是内容规则。以下元素在创建时不带子元素，SVG 类就会渲染成
自闭合形式：

- `animate`, `animateMotion`, `animateTransform`, `circle`, `ellipse`,
  `feBlend`, `feColorMatrix`, `feComposite`, `feConvolveMatrix`,
  `feDistantLight`, `feDisplacementMap`, `feDropShadow`, `feFlood`, `feFuncA`,
  `feFuncB`, `feFuncG`, `feFuncR`, `feGaussianBlur`, `feImage`, `feMergeNode`,
  `feMorphology`, `feOffset`, `fePointLight`, `feSpotLight`, `feTile`,
  `feTurbulence`, `image`, `line`, `mpath`, `path`, `polygon`, `polyline`,
  `rect`, `set`, `stop`, `use`, `view`

一旦传入子元素，元素就保持展开，因此动画元素可以嵌套 `<mpath>`
（`animateMotion(mpath()->href('#p'))`），`<use>` 也可以嵌套描述性元素；`<g>`、
`<text>`、`<feMerge>` 这类容器永远不会自闭合。`setSelfClose(true)` 仍可强制短
形式（带子元素时会抛错），`setSelfClose(false)` 强制展开形式。

```php
<?php

use function Pure\SVG\{svg, circle, rect};

$graphic = svg(
    circle()->cx('50')->cy('50')->r('40')->fill('red'),
    rect()->x('10')->y('10')->width('80')->height('80')->fill('blue')
)->width('100')->height('100');
```

## 示例

### 基本形状

```php
<?php

use function Pure\SVG\{svg, circle, rect, line, polygon};

$shapes = svg(
    // 圆形
    circle()
        ->cx('50')
        ->cy('50')
        ->r('40')
        ->fill('red')
        ->stroke('black')
        ->stroke_width('2'),

    // 矩形
    rect()
        ->x('120')
        ->y('10')
        ->width('80')
        ->height('80')
        ->fill('blue')
        ->rx('10'),

    // 线条
    line()
        ->x1('220')
        ->y1('10')
        ->x2('280')
        ->y2('90')
        ->stroke('green')
        ->stroke_width('3'),

    // 多边形（三角形）
    polygon()
        ->points('300,10 340,90 260,90')
        ->fill('yellow')
        ->stroke('orange')
        ->stroke_width('2')
)->width('400')->height('100')->viewBox('0 0 400 100');

echo $shapes;
```

### 图标

```php
<?php

use Pure\Core\SVG;

use function Pure\SVG\{svg, path};

function homeIcon(): SVG
{
    return svg(
        path('M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6')
            ->stroke('currentColor')
            ->stroke_width('2')
            ->fill('none')
            ->stroke_linecap('round')
            ->stroke_linejoin('round')
    )->width('24')->height('24')->viewBox('0 0 24 24');
}

echo homeIcon()->class('icon');
```

### 动画

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

echo $animatedCircle;
```

### 渐变和滤镜

```php
<?php

use function Pure\SVG\{svg, defs, linearGradient, stop, rect};

$gradientRect = svg(
    defs(
        linearGradient(
            stop()->offset('0%')->stop_color('#ff0000'),
            stop()->offset('100%')->stop_color('#0000ff')
        )->id('gradient1')
    ),
    rect()
        ->x('10')
        ->y('10')
        ->width('80')
        ->height('80')
        ->fill('url(#gradient1)')
)->width('100')->height('100');

echo $gradientRect;
```

### 自定义 SVG 组件

```php
<?php

use Pure\Core\SVG;

// 使用魔术方法创建自定义 SVG 元素
$customElement = SVG::customShape(
    SVG::innerPath('M10,10 L90,90'),
    SVG::customAttribute('special-value')
)->data_type('custom')->class('special-svg');

echo $customElement;
```
