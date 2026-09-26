# SVG Class

`Pure\Core\SVG` extends `Pure\Core\XML` for SVG tags. It chooses self-closing
rendering for selected childless elements, but it does not add an SVG namespace
automatically.

## Creating SVG Elements

Standard SVG tags come from the `Pure\SVG` functions; other tag names come from
the magic static surface:

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

// Any tag name, including custom elements
$custom = SVG::customShape(SVG::innerPath('M10,10 L90,90'));
```

## Namespace and Document Roots

SVG is normally an inline fragment, so `isDocumentRoot()` returns `false` and
`render()` emits only the element. No `xmlns` attribute is inferred from the
class name. Declare it yourself when writing a standalone SVG file:

```php
<?php

use function Pure\SVG\{path, svg};

$icon = svg(
    path('M3 12l2-2m0 0l7-7 7 7')
)->xmlns('http://www.w3.org/2000/svg')->viewBox('0 0 24 24');

$icon->save('icon.svg');
```

`save()` and `documentHeader()` still provide the XML declaration for the SVG
class; use an explicit header argument when a different document prologue is
required. `renderXML()` also prepends that declaration, so use `render()` for
an inline fragment. The namespace is a document concern, not an escaping
concern, and should be fixed in the template rather than supplied by untrusted
data.

## Self-Closing Tags

SVG has no void elements: `<feTile />` and `<feTile></feTile>` describe the same
document, so self-closing is a rendering style here, not a content rule. The
SVG class renders these elements self-closed when they are created without
children:

- `animate`, `animateMotion`, `animateTransform`, `circle`, `ellipse`,
  `feBlend`, `feColorMatrix`, `feComposite`, `feConvolveMatrix`,
  `feDistantLight`, `feDisplacementMap`, `feDropShadow`, `feFlood`, `feFuncA`,
  `feFuncB`, `feFuncG`, `feFuncR`, `feGaussianBlur`, `feImage`, `feMergeNode`,
  `feMorphology`, `feOffset`, `fePointLight`, `feSpotLight`, `feTile`,
  `feTurbulence`, `image`, `line`, `mpath`, `path`, `polygon`, `polyline`,
  `rect`, `set`, `stop`, `use`, `view`

Passing children keeps the element open, so animation elements can nest
`<mpath>` (`animateMotion(mpath()->href('#p'))`) and `<use>` can nest
descriptive elements; containers such as `<g>`, `<text>` or `<feMerge>` are
never self-closed. `setSelfClose(true)` still forces the short form (and rejects
children), `setSelfClose(false)` forces the open/close pair.

```php
<?php

use function Pure\SVG\{svg, circle, rect};

$graphic = svg(
    circle()->cx('50')->cy('50')->r('40')->fill('red'),
    rect()->x('10')->y('10')->width('80')->height('80')->fill('blue')
)->width('100')->height('100');
```

## Examples

### Basic Shapes

```php
<?php

use function Pure\SVG\{svg, circle, rect, line, polygon};

$shapes = svg(
    // Circle
    circle()
        ->cx('50')
        ->cy('50')
        ->r('40')
        ->fill('red')
        ->stroke('black')
        ->stroke_width('2'),
    
    // Rectangle
    rect()
        ->x('120')
        ->y('10')
        ->width('80')
        ->height('80')
        ->fill('blue')
        ->rx('10'),
    
    // Line
    line()
        ->x1('220')
        ->y1('10')
        ->x2('280')
        ->y2('90')
        ->stroke('green')
        ->stroke_width('3'),
    
    // Polygon (triangle)
    polygon()
        ->points('300,10 340,90 260,90')
        ->fill('yellow')
        ->stroke('orange')
        ->stroke_width('2')
)->width('400')->height('100')->viewBox('0 0 400 100');

echo $shapes;
```

### Icons

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

### Animations

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

### Gradients and Filters

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

### Custom SVG Components

```php
<?php

use Pure\Core\SVG;

// Create custom SVG elements using magic methods
$customElement = SVG::customShape(
    SVG::innerPath('M10,10 L90,90'),
    SVG::customAttribute('special-value')
)->data_type('custom')->class('special-svg');

echo $customElement;
```
