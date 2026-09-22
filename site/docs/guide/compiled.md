# Compiled Rendering

**Prerequisites**: [Components](/guide/components); **On this page**: how a component's template compiles, the runtime cache and performance.

Compiled rendering turns a data-free **shape** — a component's template —
into a flat PHP renderer. Static markup is escaped once at compile time and
emitted as a literal string, so rendering a page costs little more than string
concatenation plus escaping of the dynamic values — at parity with compiled
template engines.

A component's factory returns a bare tag tree of `Slot` placeholders; the
registry wraps it into a `Shape` — the same wrap `Compile::shape()` performs
for an inline tree. Templates are built **once per process** — a long-running
worker, a preloaded or CLI process, or any runtime that keeps PHP state between
requests. Under standard PHP-FPM every request starts fresh, so enable the
on-disk cache (see [Caching](#caching)) to load compiled renderers instead of
regenerating them per request, or deploy with precompiled artifacts
(see [Artifacts & Deployment](/guide/artifacts)).

## Shape, Slot, Renderer

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, li, ul};

// A shape is a normal tag tree with Slot placeholders instead of data.
$item = Compile::shape(li(Slot::value('title')));

$root = Compile::shape(
    div(
        h1(Slot::value('heading')),
        ul(Slot::each('items', $item))
    )->class('card')
);

// Rendering binds plain data.
echo $root([
    'heading' => 'Users',
    'items' => [['title' => 'Ada'], ['title' => 'Grace']],
]);
```

Output is **byte-identical** to `Tag::render()` for the same tree, because both
paths share the same escaping implementation.

| Object | Meaning |
| --- | --- |
| `Shape` | A data-free tree; `__invoke($data)`, `compile()`, `id()`, `print($data)`, `save($path, $data)` |
| `Renderer` | The compiled renderer; `render($data)`, `save($path, $data)`, and the readonly `source` / `id` / `slots` properties |
| `Slot` | A placeholder for data, bound at render time |

The slots a template can use (`Slot::value()`, `Slot::raw()`, `Slot::child()`,
`Slot::each()`, `Slot::if()`), their modifiers, value coercion and the missing
data rules are documented once in [Props and Slots](/guide/props#slot-reference);
this page does not repeat them.

## Scope and Missing Data

`Slot::child()` and `Slot::each()` create a nested data scope; inside it, slots
resolve against that scope. Missing required keys throw
`Pure\Core\MissingSlotException` with the full path, whose message suggests the
closest provided key or lists the keys the scope did provide; use
`->default($value)` or `->required(false)` for optional data — the full rules
are in [Missing Data](/guide/props#missing-data).

`Slot::if()` branches share the current scope, so this works naturally:

```php
<?php

$item = Compile::shape(
    li(
        Slot::value('name'),
        Slot::if('admin', span('(admin)'))
    )
);
```

## Components

The template of a component — a `*.cmp.php` unit with its call function, lazy
factory and `prepare()` hook (the full treatment is in
[Components](/guide/components)) — goes through this same pipeline: the
factory runs once per compile generation, the tree wraps into a shape, and the
compiled renderer is what requests reuse (see [Caching](#caching) for the
PHP-FPM case):

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

Inside a template, nested shapes use `Slot::child()`, lists use `Slot::each()`,
optional/conditional markup uses `Slot::if()`, and rendered child components
enter through `Slot::raw()`. Mixed-list dispatch happens in the data layer, see
the [Mixed Lists](#mixed-lists) appendix.

### Lists

```php
<?php

$row = Compile::shape(li(Slot::value('label')));

$shape = Compile::shape(ul(Slot::each('rows', $row)));
$shape(['rows' => [['label' => 'a'], ['label' => 'b']]]);
```

## Caching

By default the compiled renderer exists only in memory, which suits
long-running workers that keep state between requests. Under standard PHP-FPM
the shape tree is rebuilt and the renderer regenerated on every request —
slower than immediate rendering — so enable the on-disk renderer cache and
load the generated code instead of regenerating it:

```php
<?php

use Pure\Compile\Compile;

// Once, during bootstrap
Compile::cachePath(__DIR__ . '/var/cache/purephp');
```

- `Compile::cachePath($dir)` enables the on-disk renderer cache; pass `null`
  to disable (default).
- Cache files are content-addressed by `Shape::id()`; a changed shape writes a
  new file.
- Writes are atomic (temporary file + rename), so concurrent workers are safe.
- Cache files are plain PHP and opcache-friendly. The directory must be
  private: owned by the PHP user, not writable by group or others (`0700` is
  created when missing), and outside the web root — `cachePath()` rejects
  loose or foreign-owned directories; do not point it at a shared location
  like `/tmp`.
- `Compile::clearCache()` deletes the files written by the library.
- `Compile::flush()` invalidates in-memory renderers (useful in long-running
  workers after a deploy).

To catch shapes that are rebuilt per request instead of memoized, and to make
problems invisible in the output report themselves, enable the development
guard:

```php
<?php

Compile::guard(true);           // or set PURE_COMPILE_GUARD=1
```

It emits one `E_USER_WARNING` per subject per process, covering three cases:

- the same call site calls `Compile::shape()` 20 times in one process (the
  20th call warns), suggesting the `static $shape ??=` pattern;
- a binding the template never reads is reported (with a `did you mean`
  suggestion), so a misspelled key is not silently ignored;
- a setter whose attribute name is one edit away from a standard one
  (`->clas(...)`, `->hreff(...)`) warns instead of silently becoming a custom
  attribute nobody notices.

For production, enable the disk cache and build precompiled artifacts so
requests load compiled renderers — see
[Artifacts & Deployment](/guide/artifacts).

## Performance

Two costs matter per request: what a process pays to get a renderer, and what it
pays to render with it. `bench/README.md` holds the recorded rows; absolute
numbers move with the PHP version, opcache and the CPU, so run the scripts
before comparing them with the table below (PHP 8.1.34, one 604-element page of
200 rows, `php bench/compare.php`).

| Path | Time per render | End-to-end speedup |
| --- | --- | --- |
| build tree + `Tag::render()` | ~1.2 ms | 1× |
| render only (same tree reused) | ~345 µs | 3.4× |
| compiled shape + data | ~180 µs | 6.6× |
| compiled static tree (literal) | < 1 µs | — |

With opcache the build stays expensive while the compiled path barely changes, so
the speedup lands at 5.5×, and 4.6× with the JIT on. The precompiled artifact
path removes the build from that second column entirely: for one page shape,
building and compiling cost ~2.9 ms against ~25–67 µs to require its artifact
(`php bench/artifact.php --write && php bench/artifact.php`).

What a whole page costs depends on how it is composed. The bootstrap features
page builds its body from component calls, so `examples/bootstrap/bench.php`
measures the real page, not one shape: ~340 µs/op for the tag tree, ~104 µs/op
for the page function over its artifact (2.8–3.3×), and ~20 µs/op for the plain
view. The benchmark's `skeleton artifact + bindings` row renders the page
*template* with the component markup already bound, so its ~1.5 µs is a per-shape
figure, not a page render.

## Limitations

- Tag names cannot depend on data: a shape always uses the same tags. Use
  `Slot::if()` for conditional markup or dispatch mixed lists in the data layer
  ([appendix](#mixed-lists)), or normalize the data before rendering.
- Compiled code is tied to the shape structure; changing a shape changes its
  `id()` and therefore its cache file.
- A shape tree is read live while it compiles, and `id()` reflects the tree as
  it is at that moment. An already compiled renderer keeps rendering the tree
  state it was built from, so call `Compile::flush()` after mutating a tree that
  is already wrapped in a shape; building shapes once per process avoids this
  entirely.
- Shapes must not contain request data — they are process-level artifacts.

## Mixed Lists

A shape has one structure, so a list whose items need different markup is
dispatched in the data layer: render each item through the call function that
fits it and pass the joined markup into a raw slot.

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

`Slot::each()` covers the homogeneous case: one shape, every item. When the
variants are only conditional details inside one item shape, `Slot::if()` on
precomputed keys keeps the dispatch in the template.

Immediate tag-tree rendering (`Tag::render()`) remains available for snippets
and debugging; see [Basic Usage](/guide/basic-usage).

## Next Steps

- [Artifacts & Deployment](/guide/artifacts) - `pure compile` artifacts, `pure check` and plain views
- [Components](/guide/components) - Components wrap shapes
- [Props and Slots](/guide/props) - Slot types and the data-binding reference
