# API Reference

This section provides comprehensive documentation for all PurePHP classes and their methods.

## Core Classes

PurePHP consists of several core classes that work together to provide a powerful templating system:

### [Tag Class](/api/tag)
The base abstract class for all HTML and SVG tags. Provides common functionality for attributes, children, and output methods.

### [HTML Class](/api/html)
Extends Tag class specifically for HTML elements. Includes HTML-specific features like self-closing tag detection and file saving.

### [SVG Class](/api/svg)
Extends XML class for creating SVG graphics. Automatically handles SVG-specific self-closing tags and namespaces.

### [XML Class](/api/xml)
Extends Tag class for creating XML documents. Perfect for configuration files, data export, and API responses.

### [Raw Class](/api/raw)
Represents raw HTML or XML content that bypasses escaping. Useful for including pre-formatted content or templates.

### [Compiled Rendering](/api/compile)
`Pure\Compile\Compile`, `Shape` and `Renderer` compile a data-free shape tree with `Slot` placeholders into a flat PHP renderer. Static markup becomes literals, so rendering is at parity with compiled template engines while keeping the fluent PHP API.

## Quick Reference

### Creating Elements

```php
<?php

use Pure\Core\HTML;
use function Pure\HTML\div;
use function Pure\SVG\circle;

// Function approach (standard tags)
$element1 = div('Content');

// Magic static method (custom tags)
$element2 = HTML::customTag('Content');
```

### Compiling Shapes

```php
<?php

use Pure\Compile\{Compile, Shape};
use Pure\Core\Slot;

use function Pure\HTML\{div, h1};

$shape = Compile::shape(
    div(h1(Slot::value('title')))->class('card')
);

$shape->print(['title' => 'Hello']);
```

### Common Methods

All Tag-based classes share these common methods:

- `class()` / `className()` - Set CSS classes
- `style()` - Set inline styles
- `id()`, `data_*()`, `aria_*()` - Set attributes
- `getTagName()`, `getAttrs()`, `getChildren()` - Get information
- `toJSON()`, `render()`, `print()`, `__toString()` - Output methods (snippets/debugging)

`Pure\Compile\Shape` provides `__invoke($data)`, `print($data)`, `save($path, $data)` and
`compile()`; `Pure\Compile\Renderer` provides `render($data)`,
`save($path, $data)` and the readonly `source` / `id` properties.

### Performance Guidelines

- **Compile shapes once per process** — memoize them with `static $shape ??= Compile::shape(...)` (under standard PHP-FPM enable `Compile::cachePath()` so requests load the renderer instead of rebuilding it)
- **Use functions** for standard HTML/SVG tags
- **Use magic methods** for custom or dynamic tags
- **Use Raw class** for pre-formatted content
- **Enable `Compile::cachePath()`** in production so warm workers skip code generation

## Class Hierarchy

```
Tag (abstract)
├── HTML
└── XML
    └── SVG

Raw

Pure\Compile\Compile   (facade: shape, cache, guard)
Pure\Compile\Shape     (data-free tree)
Pure\Compile\Renderer  (flat renderer)
Pure\Core\Slot         (data placeholder)
```

## Next Steps

- Browse individual class documentation for detailed examples
- Read [Compiled Rendering](/guide/compiled) and [Artifacts & Deployment](/guide/artifacts) for the production path
- See [SVG and XML Support](/guide/svg-xml) for graphics and data handling
