# Utility Functions

**Prerequisites**: [Core Concepts](/guide/concepts); **On this page**: `clx()`, `sty()` and `renderHTML()` / `renderXML()`.

PurePHP provides several utility functions to simplify development: `clx()`
and `sty()` are automatically used when setting element attributes, and
`renderHTML()` / `renderXML()` prepend the document header to a rendered tree
or component call.

::: tip Utilities and compiled rendering
`clx()` and `sty()` are unchanged by compiled rendering: use them
while building static attributes in a shape, and bind dynamic values with
`Slot::value()` / `Slot::raw()` — see
[Compiled Rendering](/guide/compiled). Most examples below use the tag API,
which remains valid for snippets and debugging.
:::

## clx Function

The `clx` function is used to merge class names, supporting strings, arrays, and conditional class names.

### Basic Usage

```php
<?php

use function Pure\Utils\clx;

// Merge multiple string class names
$classes = clx('btn', 'btn-primary', 'large');
echo $classes; // Output: btn btn-primary large
```

### Conditional Class Names

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
echo $classes; // Output: btn active
```

### Array Support

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
echo $classes; // Output: btn btn-primary active
```

### Built-in Usage in class() Method

The `class()` method has a built-in `clx` function and accepts multiple parameters directly:

```php
<?php

use function Pure\HTML\div;

$isActive = true;
$size = 'large';

div('Content')
    ->class('btn', 'btn-primary', $isActive ? 'active' : null, $size)
    ->print();
```

Pass the arguments directly on the attribute; call `clx()` yourself only when
you need the merged string on its own.

## sty Function

The `sty` function converts style arrays to CSS strings.

### Basic Usage

```php
<?php

use function Pure\Utils\sty;

$styles = sty([
    'background-color' => 'red',
    'height' => '36px',
    'border' => '1px solid #fff'
]);
echo $styles; // Output: background-color: red; height: 36px; border: 1px solid #fff;
```

### Conditional Styles

```php
<?php

use function Pure\Utils\sty;

$isVisible = true;
$color = 'blue';

$styles = sty([
    'color' => $color,
    'display' => $isVisible ? 'block' : 'none',
    'opacity' => $isVisible ? 1 : 0,
    'margin' => null,  // Will be ignored
    'padding' => false // Will be ignored
]);
echo $styles; // Output: color: blue; display: block; opacity: 1;
```

### Built-in Usage in style() Method

The `style()` method has a built-in `sty` function and accepts arrays directly:

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

Pass the array directly; call `sty()` yourself only when you need the merged
style string on its own.

## renderHTML and renderXML

`renderHTML()` and `renderXML()` render a tag tree or a component call and
prepend the document header, so the result is a complete document:

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

echo renderHTML(component('Cover')); // a component call takes the same header
```

The function picks the header: `renderHTML()` always prepends `<!DOCTYPE html>`,
`renderXML()` the XML declaration, whatever the node holds. A component call
does not expose its tree, so its header can only come from the function name.
Use `->render()` when only the markup is wanted, such as for a fragment included
into a page.

## Raw Markup

Trusted markup is wrapped in `Pure\Core\Raw::of()`; the tag API emits it
verbatim. See the [Raw API](/api/raw) for details.

## Practical Examples

### Dynamic Button Component

Static configuration is a function argument; the label and the button state
are slots:

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

// Render-time values only; a null attribute is omitted.
$style = sty(['opacity' => 1, 'cursor' => 'pointer']);
echo ActionButton('Submit', 'success', 'large', false, $style);
```

### Responsive Card Component

The card accepts an HTML child, so its content is bound with `Slot::raw()`:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Raw;
use Pure\Core\Slot;

use function Pure\HTML\{div, h3, p};

function Card(string $title, iterable|string $content, string $theme = 'light', bool $featured = false): string
{
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

// `content` is trusted HTML, emitted verbatim.
echo Card('Featured Card', Raw::of('<strong>This is the content</strong> of a featured card'), 'dark', true);
```

## Next Steps

- [Basic Usage](/guide/basic-usage) - Learn basic syntax and usage
- [Components](/guide/components) - Learn how to create and use components
- [TailwindCSS Integration](/guide/tailwindcss) - Learn how to use with TailwindCSS
