# API Reference

This section documents the public rendering and component entry points. It is
an overview, not a promise that every internal helper is a stable API; see the
boundary notes on each page and the [Component API](/api/component) for the
component surface.

## Core Classes

PurePHP consists of several core classes that work together to provide a powerful templating system:

### [Tag Class](/api/tag)
The base abstract class for all HTML, XML and SVG tags. Provides common functionality for attributes, children, and output methods.

### [HTML Class](/api/html)
Extends Tag class specifically for HTML elements. Includes HTML-specific features like self-closing tag detection and file saving.

### [SVG Class](/api/svg)
Extends `XML` for creating SVG graphics and handles SVG-specific self-closing
styles. It does not add an `xmlns` attribute automatically; standalone SVG
roots must declare the namespace explicitly.

### [XML Class](/api/xml)
Extends Tag class for creating XML documents. Perfect for configuration files, data export, and API responses.

### [Raw Class](/api/raw)
Represents raw HTML or XML content that bypasses escaping. Useful for including pre-formatted content or templates.

### [Compile API](/api/compile)
`Pure\Compile\Compile`, `Shape`, `Renderer`, `Template` and `Slot` cover compiled
rendering, cache/artifact contracts and the canonical Slot reference.

### [Component API](/api/component)
`Pure\Component\component()`, `register()`, `Call`, and the contract attributes
cover the public component surface. `Registry` is marked `@internal`; use the
public helpers in application code.

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

use Pure\Compile\Compile;
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

`Pure\Compile\Shape` provides `__invoke(array $data)`, `compile()`, `id()`,
`print(array $data)`, and `save(string $path, array $data, ?string $header = null)`.
`Pure\Compile\Renderer` provides `render(array $data)`,
`save(string $path, array $data, string $header = '')` and the readonly
`source` / `id` / `slots` properties.

### Performance Guidelines

- **Compile shapes once per process** — memoize them with `static $shape ??= Compile::shape(...)` (under standard PHP-FPM enable `Compile::cachePath()` so requests load the renderer instead of rebuilding it)
- **Use functions** for standard HTML/SVG tags
- **Use magic methods** for custom or dynamic tags
- **Use Raw class** only for trusted, pre-formatted content; it bypasses escaping
- **Validate active inputs** and use a Content Security Policy for pages with trusted markup
- **Enable `Compile::cachePath()`** in production so warm workers skip code generation

## Class Hierarchy

```
Tag (abstract)
├── HTML
└── XML
    └── SVG

Markup (interface)
├── Raw
└── Call

Pure\Compile\Compile   (facade: shape, cache, guard)
Pure\Compile\Shape     (data-free tree)
Pure\Compile\Renderer  (flat renderer)
Pure\Core\Slot         (data placeholder)
Pure\Compile\Template  (template-builder attribute)
```

`Tag::export()`, `Shape::tree()`, and `Pure\Component\Registry` are implementation
details marked `@internal`; application contracts should use the public methods
and helpers listed above.

## Next Steps

- Browse individual class documentation for detailed examples
- Read [Compiled Rendering](/guide/compiled) and [Artifacts & Deployment](/guide/artifacts) for the production path
- See [SVG and XML Support](/guide/svg-xml) for graphics and data handling
