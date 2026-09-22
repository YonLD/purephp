# What is PurePHP?

**Prerequisites**: none; **On this page**: the two rendering paths and the recommended learning order.

PurePHP is a PHP template engine inspired by ReactJS functional components. You describe a UI as a tree of PHP objects that look like HTML, and PurePHP turns it into an HTML string — everything is 100% native PHP, no template syntax to learn.

PurePHP has two rendering paths:

| Path | What you write | When to use |
| --- | --- | --- |
| **Compiled rendering** | A component's data-free *template* (a shape) with `Slot` placeholders, compiled once per worker process (or loaded from the renderer cache) and rendered per request with plain data | Pages and components in production |
| **Immediate rendering** | A tag tree containing the real values, rendered on the spot with `render()` / `print()` | Snippets, prototypes, CLI tools and debugging |

**Learning order**: the [Quick Start](/guide/getting-started) gets you to a
working component; [Core Concepts](/guide/concepts) and
[Props and Slots](/guide/props) name what is underneath — a component wraps a
data-free *shape* of `Slot` placeholders — and
[Compiled Rendering](/guide/compiled) with
[Artifacts & Deployment](/guide/artifacts) covers how that template compiles
and ships.

## Why Choose PurePHP?

In traditional PHP development, the view layer often requires mixing HTML, PHP code, and other template syntax, which can be confusing for developers. PurePHP solves these problems by:

- **Pure PHP Implementation**: All code is 100% native PHP, no new template syntax to learn
- **Component-Based Development**: Reusable component shapes instead of repetitive HTML
- **HTML-like Syntax**: Tag helpers and method chaining look very close to HTML
- **Compiled Rendering**: Static markup is escaped once at compile time, so rendering costs little more than string concatenation

## Compiled Rendering

A component's template is a data-free tree, compiled once per worker process
(or loaded from the renderer cache) and rendered per request with plain data:

```php [components/Card.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Card(...),
    factory: static fn () => div(
        h2(Slot::value('title')),
        p(Slot::value('content'))
    )->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);

echo Card()->title('Title')->content('Content');
```

`register()` stores the factory lazily; a request with a fresh artifact loads
compiled code instead of rebuilding the template. Under standard PHP-FPM every
request starts fresh, so enable `Compile::cachePath()` or precompile with
`vendor/bin/pure compile`. See [Components](/guide/components) for composition,
[Compiled Rendering](/guide/compiled) for caching and
[Artifacts & Deployment](/guide/artifacts) for deployment.

## Immediate Rendering

For snippets and debugging, build a tag tree with real values and print it:

```php
<?php

use function Pure\HTML\{div, h1, p};

div(
    h1('Welcome to PurePHP'),
    p('A PHP template engine')
)->class('container')->print();
```

## Advantages

1. **Simple and Easy**: API design is simple and intuitive with a gentle learning curve
2. **Fast**: Compiled shapes render at parity with compiled template engines
3. **Type Safe**: Full support for PHP's type system, providing better development experience
4. **Lightweight**: Small core library with no unnecessary dependencies

## Next Steps

- [Quick Start](/guide/getting-started) - Install and run your first component
- [Basic Usage](/guide/basic-usage) - The tag API used by snippets
- [Core Concepts](/guide/concepts) - Tag trees, shapes, slots and components
- [Props and Slots](/guide/props) - Slot types and the data-binding reference
- [Components](/guide/components) - Components wrap shapes
- [Compiled Rendering](/guide/compiled) - How a component's template compiles
- [Artifacts & Deployment](/guide/artifacts) - `pure compile` artifacts and production deployment
