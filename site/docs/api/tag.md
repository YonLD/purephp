# Tag Class

`Pure\Core\Tag` is the base abstract class for all HTML, XML and SVG tags.

Tag trees serve two purposes:

- **Immediate rendering** (snippets and debugging): build the tree with real
  values and render it with `render()` / `print()`.
- **Compiled rendering** (production): build a data-free tree with
  `Pure\Core\Slot` placeholders, wrap it with `Pure\Compile\Compile::shape()`
  and bind data at render time. See [Compiled Rendering](./compile).

The attribute and traversal methods below are shared by both paths.

## Attribute Methods

### `class(array|bool|int|float|string|Slot|null ...$args): self`

Sets the CSS class names of the element, with the built-in `clx` helper to handle
multiple arguments. Booleans are ignored, which keeps conditional arguments
working (`->class('btn', $isActive ? 'active' : null)`). Empty strings, `null`
and empty arrays produce no `class` value. A later `class()` call with a
non-empty value replaces the previous value; a later `false`/`null` result is
ignored and does not remove an earlier class.

```php
<?php

use function Pure\HTML\div;

// Single class name
div('Content')->class('container');

// Multiple class names
div('Content')->class('btn', 'btn-primary', 'large');

// Conditional class names
$isActive = true;
div('Content')->class('btn', $isActive ? 'active' : null);

// Array format
div('Content')->class(['btn', 'btn-primary']);

// Dynamic classes (compiled rendering)
div('Content')->class(\Pure\Core\Slot::value('classList'));
```

### `className(array|bool|int|float|string|Slot|null ...$args): self`

Alias for `class()` method, since `class` is a PHP keyword.

```php
<?php

use function Pure\HTML\div;

div('Content')->className('container');
```

### `style(string|array|Slot|null $value): self`

Sets the inline styles of the element, supporting both string and array formats.

```php
<?php

use function Pure\HTML\div;

// String format
div('Content')->style('background: #fff; padding: 20px;');

// Array format (built-in sty function)
div('Content')->style([
    'background-color' => '#fff',
    'padding' => '20px',
    'border-radius' => '8px'
]);
```

## Setter Methods

### `setAttrs(array $attrs): self`

Sets multiple attributes at once.

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->setAttrs([
    'id' => 'main',
    'class' => 'container',
    'data-type' => 'card'
]);
```

Values must be scalar, `Stringable`, `Slot` or `null`; arrays raise an
`InvalidArgumentException` (use `class()`/`style()` for them). Keys are
normalized like the chained setters: `className` → `class`, `data_id` →
`data-id`. A `null` or `false` value does not clear an attribute that was set
earlier; it simply leaves the existing value untouched. Build the final value
in one call when a later value must remove an attribute.

For a boolean attribute, `true` stores the attribute name as its value and
`false` omits it. The omission is a no-op when an earlier setter already
stored the attribute: `input()->checked(true)->checked(false)` still renders
`checked="checked"`.

## Getter Methods

### `getTagName(): string`

Gets the tag name.

```php
<?php

use function Pure\HTML\div;

$element = div('Content');
echo $element->getTagName(); // Output: div
```

### `getAttrs(): array`

Gets all attributes as an associative array.

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container')->id('main');
$attrs = $element->getAttrs();
// Returns: ['class' => 'container', 'id' => 'main']
```

### `getAttr(string $key): string|Slot|null`

Gets the value of a specific attribute, or `null` when the attribute is not set.

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container');
echo $element->getAttr('class');   // Output: container
var_dump($element->getAttr('id')); // NULL
```

### `getChildren(): array`

Gets all child elements.

```php
<?php

use function Pure\HTML\{div, p};

$element = div(p('Paragraph 1'), p('Paragraph 2'));
$children = $element->getChildren();
```

## Self-Closing Tag Methods

### `getSelfClose(): bool`

Checks if the element is a self-closing tag.

```php
<?php

use function Pure\HTML\{div, img};

$div = div('Content');
echo $div->getSelfClose(); // Output: false

$img = img()->src('image.jpg');
echo $img->getSelfClose(); // Output: true
```

### `setSelfClose(bool $value): self`

Sets whether the element is a self-closing tag.

```php
<?php

use function Pure\HTML\div;

$element = div()->setSelfClose(true);
```

## Output Methods

### `toJSON(): array`

Converts the element to a nested JSON-compatible array: `tagName`, `attrs` and
`children`. Attributes live under their own key so an attribute can never
collide with the structural keys. Slots are described as `['slot' => 'name']`.

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container');
$json = $element->toJSON();
// Returns: [
//     'tagName' => 'div',
//     'attrs' => ['class' => 'container'],
//     'children' => ['Content'],
// ]
```

### `render(): string`

Renders the tag tree and its children to an HTML string directly, with real
values. Attribute values and text children are escaped while rendering;
`Pure\Core\Markup` children — `Raw` and component calls — are emitted
verbatim and rendered lazily with the tree.

`render()` (and `print()` / `__toString()`) is the **snippet and debugging**
outlet. Production pages should compile shapes instead, so static markup is
escaped once at compile time — see [Compiled Rendering](./compile).

Trees containing `Slot` placeholders cannot be rendered directly: compile them
with `Pure\Compile\Compile::shape()` and bind data at render time.

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container');
echo $element->render(); // Output: <div class="container">Content</div>
```

### `__toString(): string`

String-casts the element, equivalent to `render()`.

```php
<?php

use function Pure\HTML\div;

$element = div('Content')->class('container');
echo (string)$element; // Output: <div class="container">Content</div>
```

### `print(): void`

Directly outputs the element's HTML string.

```php
<?php

use function Pure\HTML\div;

div('Content')->class('container')->print();
// Output: <div class="container">Content</div>
```

### `save(string $path, ?string $header = null): int|false`

Writes the rendered tree to a file. When `$header` is omitted, the document
header of the tag type is prepended (`<!DOCTYPE html>` for HTML, the XML
declaration for XML and SVG); pass `$header` to override it. Returns the number
of bytes written, or `false` on failure.

```php
<?php

use function Pure\HTML\{div, h1};

div(h1('Report'))->save('report.html');
```

### `documentHeader(): string`

Returns the default header for the tag type: `<!DOCTYPE html>` for `HTML` and
`<?xml version="1.0"?>` for `XML`/`SVG`. `render()` does not prepend it; `save()`
uses it unless an explicit `$header` is supplied.

### `isDocumentRoot(): bool`

Whether this tag heads a complete document, so a compiled plain view is preceded
by its document header. An HTML tree is a document only when its root is
`<html>`, and an XML tree always is; an SVG tree is a fragment (icons are
inlined), whose standalone-file header stays available through
`documentHeader()` / `save()`. SVG does not add `xmlns` automatically; declare
it explicitly on a standalone root.

## Escaping Boundary

`render()` escapes text children and normal attribute values, but this is a
syntax boundary rather than a complete web-security policy. It does not validate
URL schemes (`href`/`src`), event-handler attributes, inline styles, or dynamic
tag names, and `Raw`/`Markup` children are emitted verbatim. Validate active
inputs, restrict custom tag names, and use a browser Content Security Policy
when a page contains scripts or other active markup.

## Dynamic Attribute Methods

The Tag class supports dynamically setting ordinary HTML attributes through the
`__call` magic method. A name containing a namespace separator (for example
`xlink:href` or `xmlns:soap`) is not a plain method name; set it with the
dynamic call form `->{'xlink:href'}('...')`, or pass it as a key through
`setAttrs()`.

```php
<?php

use function Pure\HTML\{div, input, img};

// Set ID
div('Content')->id('main');

// Set data attributes (note the use of underscores)
div('Content')->data_id('123')->data_type('card');

// Set ARIA attributes
div('Content')->aria_label('Main content');

// Set form attributes
input()->type('text')->name('username')->placeholder('Enter username');

// Set image attributes
img()->src('image.jpg')->alt('Image description')->width('100')->height('100');
```

With the development guard on (`Compile::guard(true)` /
`PURE_COMPILE_GUARD=1`), a setter whose name is one edit away from a standard
attribute warns once — `->clas(...)` and `->hreff(...)` suggest `class` and
`href` instead of silently becoming custom attributes. Set custom attributes on
purpose with the same syntax; the warning is advisory and off by default.
