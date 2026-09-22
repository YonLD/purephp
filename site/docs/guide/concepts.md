# Core Concepts

**Prerequisites**: [Quick Start](/guide/getting-started); **On this page**: tag trees, shapes, slots, components and data scope.

This guide explains the core concepts of PurePHP: tag trees and Slot first,
Shape as the data-free template a component renders, and components — the
recommended path for any data-driven output — toward the end of this page.

## Choosing a Path

One rule covers both paths:

- **Data drives the output → components**: the template is a data-free tree
  of `Slot` placeholders and props bind plain data per request — see
  [Components](/guide/components). This is the recommended path for pages.
- **A snippet, prototype or debug output → render immediately**: build the tree
  with the real values and call `print()` or `render()`.

| Action | `Tag` (immediate) | `Shape` (compiled) |
| --- | --- | --- |
| String | `render()` | `$shape($data)` |
| Output | `print()` | `print($data)` |
| File | `save($path)` | `save($path, $data)` |
| Debug | `toJSON()` | `compile()->source` |

Each name exists once: `render()` returns a string, `print()` echoes, `save()`
writes a file (prepending the document header of the root tag) and `toJSON()` /
`source` expose the structure for debugging.

## Tag Trees

PurePHP represents HTML with PHP objects. Tag helper functions build a tree, and
method chaining sets attributes:

```php
<?php

use function Pure\HTML\{div, h1, p};

$element = div(
    h1('Title'),
    p('Content')
)->class('container');

echo $element; // <div class="container"><h1>Title</h1><p>Content</p></div>
```

Text children and attribute values are escaped while rendering; `Raw` children
are emitted verbatim. A tag tree that contains data is rendered immediately
with `render()`, `print()` or `__toString()`. That path is the right tool for
snippets and debugging.

## Shapes and Slots

A **shape** is the same kind of tree, but *data-free*: dynamic values are
replaced by `Slot` placeholders. A shape describes structure; data arrives
later. A component's template is a shape — this section shows the form on its
own so the Slot vocabulary stands alone, and the Components section below wraps
it into the recommended unit.

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, p};

$shape = Compile::shape(
    div(
        h1(Slot::value('heading')),
        p(Slot::value('lead'))
    )->class('container')
);
```

Slot types:

| Slot | Binds | Creates a nested scope |
| --- | --- | --- |
| `Slot::value()` | scalar / `null` / `Stringable` | no — child position escapes to text; attribute position follows `setAttr()` semantics (`true`→`name="name"`, `false`/`null` omitted) |
| `Slot::raw()` | stringable value (or a list of them), verbatim | no |
| `Slot::child()` | array | yes |
| `Slot::each()` | iterable of arrays | yes, per item |
| `Slot::if()` | truthy condition | no (branches share the scope) |

A slot name is always the **data key** (and the error path), never a tag or
attribute name: in `a(Slot::value('label'))->class(Slot::value('classList'))` the
text binds `label` and the class attribute binds `classList`, while `a` and
`class` come from the tree.

## Compiling

`Compile::shape()` wraps a tree as a `Shape`. The first render compiles it into
a flat PHP closure: static markup becomes a literal string, escaping is done
once, and only slots remain as runtime work.

```php
<?php

$shape([
    'heading' => 'Welcome',
    'lead' => 'Compiled rendering',
]);
```

Key properties:

- **Compiled once per process** — memoize shapes in `static` variables inside
  the function that builds them, never build them inside a request handler. Under
  standard PHP-FPM statics reset every request, so enable
  `Compile::cachePath()` to load compiled renderers instead of regenerating
  them.
- **Byte-identical output** — the compiled path and `Tag::render()` share the
  same escaping implementation.
- **Optional disk cache** — `Compile::cachePath($dir)` stores compiled
  renderers so warm workers load code instead of generating it.

`Shape::id()` is a structural fingerprint (tags, attributes, slots and nested
shapes) that is available without compiling; it names the on-disk cache file and
keys the in-memory memo of generated code. A precompiled artifact carries it in
its header, while `pure compile --check` recognises a stale artifact by
comparing its content with the freshly generated source byte for byte.
Components are resolved by their registered name or unit file, not by it.

## Data Binding and Scope

Rendering a shape binds plain data:

```php
<?php

$list = Compile::shape(ul(Slot::each('items', li(Slot::value('title')))));

$list(['items' => [['title' => 'a'], ['title' => 'b']]]);
```

`Slot::child()` and `Slot::each()` establish a nested scope, so inside `li` the
slot `title` resolves against the current item. Missing required keys throw
`Pure\Core\MissingSlotException` with the full path, whose message suggests the
closest provided key or lists the keys the scope did provide; use
`->default($value)` or `->required(false)` for optional data — the full rules
are in [Missing Data](/guide/props#missing-data).

## Components

A component is a wrapper around a shape: one `*.cmp.php` unit holds a call
function that returns a `Pure\Component\Call`, the template (a shape) it
renders, and the `prepare()` hook that types its props:

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
    factory: static fn () =>
        div(
            h2(Slot::value('title')),
            p(Slot::value('content'))
        )->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);

echo Card()->title('Title')->content('Content');
```

See [Components](/guide/components) for composition,
[Compiled Rendering](/guide/compiled) for caching and the per-request guard,
and [Artifacts & Deployment](/guide/artifacts) for artifacts and production
deployment.

## State Management

State is plain PHP: values are passed into the shape as data.

### Simple State

```php [components/Counter.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\span;

function Counter(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Counter(...),
    factory: static fn () => span(Slot::value('count'))->id('counter'),
    prepare: static function (int $count): array {
        return ['count' => $count];
    }
);

echo Counter()->count(0);
```

### Global State

Any PHP store works; assemble the data array and pass it to the template:

```php
<?php

class Store
{
    private static array $state = [];

    public static function set(string $key, mixed $value): void
    {
        self::$state[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        return self::$state[$key] ?? null;
    }
}

Store::set('user', ['name' => 'John']);
$user = Store::get('user');
```

## Conditional Lists

The main table above covers the everyday slots. A list whose items need
different markup is dispatched in the data layer: render each item through the
call function that fits it and pass the joined markup into a raw slot.

```php
<?php

function Blocks(array $blocks): string
{
    $html = '';

    foreach ($blocks as $block) {
        $html .= $block['kind'] === 'link'
            ? LinkBlock($block['value'], $block['href'])
            : TextBlock($block['value']);
    }

    return $html;
}

$blocks = [
    ['kind' => 'link', 'value' => 'Docs', 'href' => '/docs'],
    ['kind' => 'text', 'value' => 'Hello'],
];

$shape = Compile::shape(div(Slot::raw('blocks')));
$shape(['blocks' => Blocks($blocks)]);
```

See [Mixed lists](/guide/compiled#mixed-lists) in the compiled guide for the
full treatment.

## Next Steps

- [Props and Slots](/guide/props) - How data is bound to shapes
- [Components](/guide/components) - Components wrap shapes: the recommended path
- [Compiled Rendering](/guide/compiled) - How a component's template compiles
- [Artifacts & Deployment](/guide/artifacts) - `pure compile` artifacts and production deployment
