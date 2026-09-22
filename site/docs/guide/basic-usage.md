# Basic Usage

**Prerequisites**: [Quick Start](/guide/getting-started); **On this page**: the tag API — creating elements, setting attributes, immediate rendering.

::: tip Tag API vs. compiled rendering
This page documents the tag API used for snippets, prototypes, and debugging; trees built this way render immediately via `render()` / `print()`. Production pages should compile shapes instead — see [Compiled Rendering](/guide/compiled).
:::

## Basic Syntax

### 1. Creating HTML Elements

PurePHP provides two ways to create HTML elements:

#### Function Approach (Recommended for predefined tags)

PurePHP uses function calls to create HTML elements:

```php
<?php

use function Pure\HTML\div;
use function Pure\HTML\h1;
use function Pure\HTML\p;

// Create a simple div element
div('Hello World')->print();

// Create nested elements
div(
    h1('Title'),
    p('Paragraph content')
)->class('container')->print();
```

#### Magic Static Methods (For custom tags)

```php
<?php

use Pure\Core\HTML;

// Create custom HTML elements using magic methods
// (the method name is the tag name: this renders <customTag class="custom">)
HTML::customTag('Custom content')->class('custom')->print();

// Perfect for web components or non-standard tags
HTML::myComponent(
    HTML::header('Component Header'),
    HTML::content('Component Body')
)->data_component('my-component')->print();
```

Custom tags take the same children arguments as the functions, so a dynamic
tag name works too:

```php
<?php

use Pure\Core\HTML;

$tag = 'my-element';
HTML::{$tag}('Content')->class('dynamic')->print();
```

### 2. Setting Attributes

Use method chaining to set element attributes:

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

## Choosing the Right Approach

### When to Use Each Method

#### Use Functions (Recommended for most cases)
- **Best for**: Standard HTML tags, everyday development
- **Advantages**: Clean syntax, good performance, excellent readability
- **Example**: `div()`, `p()`, `span()`, etc.

#### Use Magic Static Methods
- **Best for**: Custom tags, web components, dynamic tag names
- **Advantages**: Works with any tag name, elegant syntax
- **Example**: `HTML::customElement()`, `HTML::webComponent()`

```php
<?php

use function Pure\HTML\div;
use Pure\Core\HTML;

// Function approach - standard tags
$standard = div('Standard content')->class('container');

// Magic method - custom tags
$custom = HTML::myCustomTag('Custom content')->data_component('special');
```

## Important Usage Notes

### 1. String Content vs Raw Content

String children are always escaped, so they are safe for user input and never
lose data: comparison text such as `2<3` or `a<b` stays visible. Markup-looking
strings are shown as text instead of being parsed:

```php
<?php

use Pure\Core\Raw;

use function Pure\HTML\div;

// ✅ String content is escaped, not parsed
div('<p>This is shown as text</p>')->print();
// Output: <div>&lt;p&gt;This is shown as text&lt;/p&gt;</div>

// ✅ Use Raw::of to emit trusted markup
div(Raw::of('<p>This is preserved</p>'))->print();
// Output: <div><p>This is preserved</p></div>
```

**Why this matters:**
- **Security**: Escaping neutralizes XSS in user input
- **No data loss**: Text that merely looks like markup is kept verbatim
- **Intentionality**: Emitting markup requires an explicit Raw::of() wrapper

**When to use Raw::of():**
- Including pre-formatted HTML/XML content
- Embedding templates or external content
- Working with trusted HTML/XML strings
- Including JavaScript or CSS code blocks

### 2. className Alias

Since `class` is a PHP keyword, PurePHP provides `className` as an alias:

```php
<?php

use function Pure\HTML\div;

// Both ways work
div('Content')->class('container')->print();
div('Content')->className('container')->print();
```

### 3. Built-in Utility Functions

The `class()` method has a built-in `clx` function, and `style()` has a built-in `sty` function to handle arrays and conditional parameters:

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

When you need to merge class/style strings on their own, reach for `clx()` /
`sty()` from [Utility Functions](/guide/utils).

### 4. Attribute Naming Rules

Since `-` has special meaning in PHP, attributes like `data-id` need to be written as `data_id`:

```php
<?php

use function Pure\HTML\div;

div('Content')
    ->data_id('123')           // corresponds to data-id="123"
    ->data_type('card')        // corresponds to data-type="card"
    ->aria_label('Button')     // corresponds to aria-label="Button"
    ->print();
```

### 5. Adding Child Elements

You can add multiple child elements through parameters:

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

## Common HTML Tags

PurePHP supports all common HTML tags:

```php
<?php

use function Pure\HTML\{
    div, span, p, h1, h2, h3, h4, h5, h6,
    a, img, ul, ol, li, table, tr, td, th,
    form, input, button, textarea, select, option
};

// Create a link
a('Click here')->href('https://example.com')->print();

// Create an image
img()->src('image.jpg')->alt('Image description')->print();

// Create a list
ul(
    li('Item 1'),
    li('Item 2'),
    li('Item 3')
)->class('list')->print();

// Create a form
form(
    input()->type('text')->name('username'),
    input()->type('password')->name('password'),
    button('Submit')->type('submit')
)->method('POST')->action('/login')->print();
```

## SVG Support

PurePHP has built-in support for SVG tags:

```php
<?php

use function Pure\SVG\{svg, circle, rect, path};

// Create a simple circle
svg(
    circle()
        ->cx('50')
        ->cy('50')
        ->r('40')
        ->stroke('black')
        ->stroke_width('3')
        ->fill('red')
)->width('100')->height('100')->print();

// Create a rectangle
svg(
    rect()
        ->x('10')
        ->y('10')
        ->width('80')
        ->height('80')
        ->fill('blue')
)->width('100')->height('100')->print();
```

## Conditional Rendering

Use PHP conditional statements for conditional rendering:

```php
<?php

use function Pure\HTML\{div, p};

$isLoggedIn = true;

div(
    $isLoggedIn ? p('Welcome back!') : p('Please log in')
)->class('message')->print();
```

With compiled rendering, the condition becomes a `Slot::if()` placeholder and
the branches are shapes. Slots such as `Slot::value()` stand in for the values
that are bound at render time:

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

`Slot::if()` reads the current data scope, a missing key is false, and the
shape is memoized in `static` so it is built once per process.

## Loop Rendering

Use PHP loop statements to render lists:

```php
<?php

use function Pure\HTML\{ul, li};

$items = ['Apple', 'Banana', 'Orange'];

ul(
    ...array_map(fn($item) => li($item), $items)
)->class('fruits')->print();
```

With compiled rendering, lists are `Slot::each()` slots: the item shape is
rendered for every element of the bound iterable, with `Slot::value()` marking
the value to bind:

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

Each item is an array supplying the slot names used by the item shape; requests
only bind data to the already compiled shape.

## Style Handling

### 1. Inline Styles

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

### 2. Class Name Handling

One `class()` call joins all of its arguments, and a second `class()` call
replaces the value of the first, so pass every class in a single call:

```php
<?php

use function Pure\HTML\div;

$isActive = true;

div('Content')
    ->class('container', $isActive ? 'active' : 'inactive')
    ->print();
```

## Next Steps

- [Core Concepts](/guide/concepts) - Tag trees, shapes, slots and components
- [Props and Slots](/guide/props) - Slot types and the data-binding reference
- [Components](/guide/components) - Components wrap shapes
- [Compiled Rendering](/guide/compiled) - How a component's template compiles
- [SVG and XML Support](/guide/svg-xml) - Learn about SVG graphics and XML documents
