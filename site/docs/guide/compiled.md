# Compiled Rendering

**Prerequisites**: [Components](/guide/components); **On this page**: how a component's template compiles, the runtime cache and performance.

Compiled rendering turns a data-free **shape** — a component's template —
into a flat PHP renderer. Static markup is escaped once at compile time and
emitted as a literal string, so rendering a page costs little more than string
concatenation plus escaping of the dynamic values — at parity with compiled
template engines.

A component's factory returns a bare tag tree of `Slot` placeholders; the
registry wraps it into a `Shape` — the same wrap `Compile::shape()` performs
for an inline tree. Templates are built **once per process** — in a long-running
worker, a CLI process, or another runtime that keeps PHP state between requests.
`opcache.preload` can keep code in memory but does not preserve static shape
state across requests. Under standard PHP-FPM every request starts fresh, so
enable the on-disk cache (see [Caching](#caching)) to load compiled renderers
instead of regenerating them per request, or deploy with precompiled artifacts
(see [Artifacts & Deployment](/guide/artifacts)).

## Shape, Slot, Renderer

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, li, span, ul};

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

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{li, span};

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

function Card(): Call
{
    return component(__FUNCTION__);
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

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{li, ul};

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
- Cache files are content-addressed by `Shape::id()`; a changed shape or PHP
  minor writes a new file.
- Writes are atomic (temporary file + rename), so concurrent workers are safe.
- Cache files are plain PHP and opcache-friendly. `cachePath()` creates a
  missing directory with mode `0700`, rejects group/other-writable modes, and,
  when the POSIX owner API is available, rejects a directory owned by another
  user. It does not check whether the path is outside the web root, so keep the
  cache in a dedicated directory outside the document root; do not point it at
  a shared location like `/tmp`.
- `Compile::clearCache()` deletes the files written by the library.
- `Compile::flush()` invalidates in-memory renderers (useful in long-running
  workers after a deploy).

To catch shapes that are rebuilt per request instead of memoized, and to make
problems invisible in the output report themselves, enable the development
guard:

```php
<?php

use Pure\Compile\Compile;

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

This section is the canonical recorded performance reference for the
documentation. The snapshot below comes from
[`bench/README.md` at commit `c9b33e3`](https://github.com/YonLD/purephp/blob/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench/README.md#recorded-numbers),
with the benchmark sources pinned to
[that same commit](https://github.com/YonLD/purephp/tree/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench).
`bench/compare.php` used 200 rows (about 604 rendered elements) for 3000
iterations on an AMD Ryzen 5 7500F running Linux with PHP 8.4.24 CLI (NTS,
opcache 8.4.24), recorded 2026-09-16.

| Path | No opcache | Opcache | Opcache + JIT |
| --- | ---: | ---: | ---: |
| build tree + `Tag::render()` | 631.0 µs | 608.3 µs | 433.8 µs |
| compiled shape + data | 141.7 µs | 135.2 µs | 107.9 µs |
| render only (tree reused) | 224.2 µs | 212.4 µs | 163.5 µs |
| compiled static tree (literal) | 0.1 µs | 0.1 µs | 0.2 µs |
| end-to-end speedup | 4.5× | 4.5× | 4.0× |

Treat these as one recorded run, not a promise for another CPU or PHP build. The
same source snapshot also documents artifact loading (`bench/artifact.php`),
renderer-cache behavior (`bench/cache.php`), component loading
(`bench/registry.php`) and a full page (`examples/bootstrap/bench.php`). Use
those scripts on the target deployment before choosing between workers, the
runtime cache and precompiled artifacts.

The supporting rows from that snapshot: a precompiled artifact takes 38–67 µs to
`require` cold and ~25 µs warm, at 1.4–2.5 µs per render (`bench/artifact.php`);
the renderer cache takes ~0.78 ms cold and ~0.26 ms warm (`bench/cache.php`);
with opcache, 22 component artifacts load in 10–14 µs in total
(`bench/registry.php`); and the example page runs ~104 µs as a page function and
~20 µs as a plain view (`examples/bootstrap/bench.php`). Validating content costs
more than compiling it: hashing every unit measured ~7 µs per file and reading
each artifact header ~4 µs, against ~0.5 µs to load the artifact with opcache.

## Trusted Markup and Deployment

`Slot::raw()` and component `Markup` children are emitted verbatim. Treat them
as trusted inputs, validate URL schemes and active attributes, and use a browser
Content Security Policy when a page intentionally includes scripts or styles.
Escaping in compiled text/value slots protects syntax; it is not a sanitizer for
URLs or user-provided markup. Precompiled `*.pure.php` files are executable PHP
and should be built from trusted source with restricted write access.

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
dispatched in the data layer: build each item's markup there and pass the
joined result into a raw slot.

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

function Blocks(array $blocks): string
{
    $html = '';

    foreach ($blocks as $block) {
        $html .= $block['kind'] === 'link'
            ? sprintf(
                '<a href="%s">%s</a>',
                htmlspecialchars($block['href'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($block['value'], ENT_QUOTES, 'UTF-8')
            )
            : htmlspecialchars($block['value'], ENT_QUOTES, 'UTF-8');
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
