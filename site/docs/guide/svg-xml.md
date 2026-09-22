# SVG and XML Support

**Prerequisites**: [Basic Usage](/guide/basic-usage); **On this page**: building SVG graphics and XML documents, and compiling them for export.

PurePHP provides comprehensive support for creating SVG graphics and XML documents with the same elegant syntax as HTML.

::: tip Two paths for SVG and XML
HTML, SVG and XML tag instances all extend `Tag`, so any of them can be wrapped in `Compile::shape()` and rendered with data — see [Compiled Rendering](/guide/compiled). The SVG sections below are a tag-API reference and render immediately with `render()` / `print()`; the XML sections use the compiled path.
:::

## SVG Support

### Basic SVG Creation

Create SVG graphics with the `Pure\SVG` functions, or with magic static methods
for custom tags:

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

echo $graphic; // Outputs SVG markup
```

### Functions vs Magic Static Methods

**Use functions when:**
- The tag is one of the predefined HTML/SVG tags
- Working with dynamic values (children and attributes)

**Use magic static methods when:**
- Creating custom or non-standard tags
- Working with dynamic tag names

Both build the same `Tag` object; the functions are the thin, explicit wrapper
for the common names. Custom tags use the magic static surface:

```php
<?php

use Pure\Core\SVG;

// Perfect for non-standard or custom SVG elements
$customElement = SVG::customTag(SVG::innerElement('content'))
    ->customAttribute('value');

// Works with dynamic tag names too
$tag = 'myCustomSvgElement';
$webComponent = SVG::{$tag}()
    ->data_id('unique')
    ->class('custom-svg');
```

### Complex SVG Examples

#### Creating Icons

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

#### Animated SVG

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

## XML Support

XML tags extend `Tag` as well, so a document is built as a compiled shape: the
tree and its slots are created once per process, and each export binds data and
saves or prints it.

### Compiled XML Documents

`Address()` renders one record; `city` is optional and only appears when the
data provides it:

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

`Slot::if()` skips the `city` element for records without it — a missing key is
false and never throws. To write the document to a file, use `Shape::save()`,
which prepends the document header of the root tag by default (a custom header
is its third argument).

### Data-driven Elements

Tag names are fixed at build time, so dynamic keys and values become slots —
here `key` attributes and text content over a list of settings:

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

When the structure itself has to vary with the data, use `Slot::if()` or
dispatch in the data layer; the tag set of a shape cannot.

## Important: String Content Is Escaped

⚠️ **Security Note**: String content is always escaped, so XML/SVG-looking text
is safe and stays visible:

```php
<?php

use Pure\Core\Raw;
use Pure\Core\XML;

// ✅ XML tags in strings are escaped, not parsed
XML::root('<item>This stays visible</item>')->print();
// Output: <root>&lt;item&gt;This stays visible&lt;/item&gt;</root>

// ✅ Use Raw::of to emit XML content
XML::root(Raw::of('<item>This is preserved</item>'))->print();
// Output: <root><item>This is preserved</item></root>
```

**When to use Raw::of():**
- Including CDATA sections
- Embedding external XML/SVG content
- Working with pre-formatted markup
- Including complex nested structures

Both render paths behave the same way; bound data is escaped by
`Slot::value()`, and `Slot::raw()` is the verbatim equivalent of `Raw::of()`
when data must keep its markup.

## Best Practices

1. **Use functions for predefined HTML/SVG tags** - They provide the best balance of performance and readability
2. **Use magic methods for custom tags** - When you need dynamic tag creation
3. **Use Raw::of() for trusted content** - When you need to preserve markup structure
4. **Combine approaches as needed** - You can mix and match based on your specific use case

## Next Steps

- [API Reference](/api/) - Complete API documentation
- [Components](/guide/components) - Learn about creating reusable components
- [Utility Functions](/guide/utils) - Explore helper functions
