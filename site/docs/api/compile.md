# Compile API

`Pure\Compile\Compile` compiles a data-free **shape** tree into a flat PHP
renderer. Static markup is escaped once at compile time and emitted as literal
string chunks, so rendering a page costs little more than string concatenation
plus escaping of the dynamic values.

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, li, p, ul};

// build + compile once per process
$item  = Compile::shape(li(Slot::value('title')));
$shape = Compile::shape(
    div(
        h1(Slot::value('heading')),
        ul(Slot::each('items', $item))
    )->class('card')
);

// render per request with plain data
echo $shape([
    'heading' => 'Users',
    'items' => [['title' => 'Ada'], ['title' => 'Grace']],
]);
```

Output is **byte-identical** to `Tag::render()` for the same tree, because both
paths share the same escaping implementation (`Pure\Core\Escaper`, `@internal`).

## Classes

| Class | Purpose |
| --- | --- |
| `Pure\Compile\Compile` | Facade: `shape(Tag $shape): Shape`, `cachePath(?string $dir): void`, `clearCache(): int`, `flush(): void`, `guard(bool $enabled = true): void` |
| `Pure\Compile\Shape` | A data-free tree: `__invoke(array $data): string`, `compile(): Renderer`, `id(): string`, `print(array $data): void`, `save(string $path, array $data, ?string $header = null): int|false` |
| `Pure\Compile\Renderer` | The compiled renderer: `render(array $data): string`, `save(string $path, array $data, string $header = ''): int|false` and readonly `source`, `id`, and `slots` properties |
| `Pure\Core\Slot` | Placeholder constructors (`value`, `raw`, `child`, `each`, `if`) and modifiers |
| `Pure\Compile\Template` | Attribute that marks a template builder for `pure compile --list` and `pure check` |
| `Pure\Core\MissingSlotException` | Thrown when a required slot is missing, with the full path |

## Component Units

A component unit registers a lazy template factory under the name of its call
function: the call function returns a `Call` and carries the component name
exactly once (`component(__FUNCTION__, ...)`), and the unit's `prepare()` hook
is the typed prop contract. A call produces the markup on string conversion:

```php
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
        div(h2(Slot::value('title')), p(Slot::value('content')))->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);

echo Card()->title('Title')->content('Content');
```

| Function or type | Behavior |
| --- | --- |
| `register(Closure $call, ?Closure $factory = null, bool $override = false, ?Closure $prepare = null): void` | Registers a component unit. Pass the call function — `register(Card(...), $factory)` — and the name and file derive from it. The factory must be lazy and may return a tag tree or a `Shape`, and `$prepare` is the optional typed props-to-bindings hook of a fluent call |
| `component(string $name, mixed ...$children): Call` | Starts a fluent call: props are set like tag attributes, children bind the reserved `children` slot, and the result is `Markup`, so it nests like a tag; `$name` is a registered name or a template path, and a unit's own call function passes `__FUNCTION__` |
| `Call::props(array $props): self` | Sets several named props at once; values follow the same rules as the fluent setters. `Call` is created through `component()`, not by calling its internal constructor directly. |

There is no page flavour: to emit a full document, pass the tag tree or the
component call to `Pure\Utils\renderHTML()` / `renderXML()`, or prepend the
header yourself — the root tag's `documentHeader()`, or the
`HTML::DOCUMENT_HEADER` / `XML::DOCUMENT_HEADER` constants.

A fluent call binds one prop per setter (`Card($children)->title($title)`);
`null` leaves a prop unset, and children bind the reserved `children` slot
(`Slot::raw('children')`). `Call` also provides `props(array $props): self` for
setting a record of named props in one call. A later `null` does not clear a
value that an earlier setter already stored; choose the final value before
setting it. A generic component prop keeps `false` as data; the template's Slot
decides how that value is rendered. The special `class()` and `style()` setters
retain their tag-style joining rules.

`Call` implements `Pure\Core\Markup`, and so does `Raw`; a `Markup` child is
emitted verbatim and renders lazily with the tree, while every other child is
frozen to text and escaped. A component call cannot be part of a data-free shape
— render it into a raw slot instead.

The public application entry points are `register()` and `component()`.
`Pure\Component\Registry` is marked `@internal`; its binder and cache methods
may be used by the implementation, but their signatures are not an application
compatibility promise. The source accepted by `component()` is a registered
name, the path of a `*.cmp.php` unit, or the path of a `*.shape.php` template.
Registering the same name for another file, or another name for the same file,
throws unless `override: true` is passed; one unit file registers one
component. A name and the path of its unit file resolve to the same binder.

For a file, the sibling `*.pure.php` artifact is loaded when it exists and is at
least as new as the unit or shape file, so production skips calling the factory
and building the shape tree; otherwise the factory runs (once per compile
generation) or the shape file is compiled (the disk cache still applies).
Missing files, a template that does not return a tag tree or a `Shape` and an
artifact that does not return a `Renderer` all raise a `RuntimeException` naming
the file. Run `pure compile` to build artifacts for every `*.shape.php` and
`*.cmp.php` file. `pure compile --list` prints the exact discovered labels:
`name -> file (component)`, `file (shape)`, and `name -> file (template)` for
builders marked with `#[Template]`; it does not compile. `pure compile --check`
keeps artifacts fresh in CI. `pure check` validates component contracts
(slots against bindings and parameter types) without writing artifacts, but it
first `require`s each unit and invokes its factory (when present) to build the
shape. Run it only on trusted source in an isolated CI process.

## Shape vs. Data

A shape is a normal tag tree in which dynamic values are replaced by `Slot`
placeholders. Shapes must not contain request data, and should be built **once
per process** — file-backed templates get that from the internal per-path binder
cache, while inline trees use a `static` variable inside the function that
builds them, never a request handler.

A child component's markup enters a template through a raw slot — a bare string
child would be escaped as text:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, li};

$row = Compile::shape(li(Slot::value('title')));
$shape = Compile::shape(
    div(Slot::raw('header'), Slot::each('rows', $row))->class('page')
);
$shape([
    'header' => '<header>Users</header>',
    'rows' => [['title' => 'Ada']],
]);
```

## Slot Types

The canonical Slot table lives in [Props and Slots](/guide/props#slot-reference).
It is the single reference for constructor values, position-sensitive
value/raw semantics, modifiers and path-bearing errors; this API page does not
duplicate it.

Keep these two boundaries in mind when using the API:

- `required(false)` and `default($value)` make a value or raw slot optional, but
  an explicit `null` is still invalid for a required value/raw slot in
  child/text position. In attribute position, `Slot::value()` follows
  `setAttr()` and omits `null`; raw cannot be used as an attribute value. For
  `child` and `each`, the fallback must still be an array or an iterable;
  `default(null)` does not turn a container into an empty one. A static
  analysis docblock may still include `|null` for an optional container, but
  the runtime accessor keeps the type check.
- `Slot::value()` escapes text and normal attributes according to its position;
  `Slot::raw()` emits its value verbatim. Escaping is not URL validation or a
  substitute for a Content Security Policy.

## Static Subtree Folding

A subtree that contains no slots is static markup. The compiler folds it into a
single literal by rendering it once at compile time, so such subtrees cost
nothing at render time:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

$shape = Compile::shape(div(
    Slot::raw('header'),
    div('Static footer')->class('footer'),
    Slot::value('title')
));
```

`div('Static footer')` is folded into a literal; `header` and `title` stay
dynamic, and already-rendered `Header()` markup enters through the raw slot at
render time.

## Structure Fingerprint

`Shape::id()` is a SHA-1 fingerprint of the shape's structure: tag names,
attribute names and values, slot kinds and names, defaults, nested shapes, the
library cache version, and the running `PHP_MAJOR_VERSION.PHP_MINOR_VERSION`.
It is computed without compiling, and it keys the on-disk renderer cache: the
generated source is stored under it, so two shapes that differ anywhere in the
structure or PHP minor cannot share a cached renderer. A `*.pure.php` artifact
records it in its header; `pure compile --check` recognises a stale artifact by
comparing the artifact with the freshly generated source byte for byte. It is
not what the public `component()` helper resolves a component by — that is the
registered name or the unit file — and it does not change when only the *data*
changes. Because the PHP minor is part of the salt, build artifacts and caches
with the same PHP minor used in production; never copy them across minor
versions. Where it does help is an application that assembles a different shape
per variant: within one PHP minor, the fingerprint is a cheap, deterministic key
for the memo it keeps them in:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

$classList = 'card';
$shapes = [];
$variant = Compile::shape(div(Slot::value('label')));
$shapes[$classList . '|' . $variant->id()] ??= $variant;
```

## Renderer API

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

$shape = Compile::shape(div(Slot::value('title')));
$data = ['title' => 'Users'];
$path = 'users.html';
$header = '';
$compiled = $shape->compile();

$compiled->render($data);                    // string
$compiled->save($path, $data);               // write to file, returns bytes written
$compiled->save($path, $data, $header);      // prepend $header to the file
$compiled->source;                           // generated PHP source (empty for precompiled artifacts)
$compiled->id;                               // structure fingerprint (same as Shape::id())
$compiled->slots;
```

`Shape::save($path, $data)` is the user-facing shortcut: it writes the rendered
output, prepending the document header of the root tag (for example
`<!DOCTYPE html>` or the XML declaration) unless you pass your own header.
`Renderer::save()` instead takes the header as an optional third parameter,
empty by default, so a handler that wants a whole document prepends it itself:
`HTML::DOCUMENT_HEADER . $renderer->render($data)`.

## On-Disk Cache

Disabled by default. Enable it once during bootstrap:

```php
<?php

use Pure\Compile\Compile;

Compile::cachePath(__DIR__ . '/var/cache/purephp');
```

- Cache files are named by `Shape::id()`, written atomically (temp file +
  rename) and contain plain PHP returning the compiled closure, so opcache can
  serve them.
- A cache entry whose header does not match the expected id, cache version or
  PHP version is discarded and regenerated.
- `Compile::clearCache()` deletes the files written by the library.
- Generated sources are memoized per fingerprint in memory as well, so building
  the same tree again in one process re-evaluates the cached source instead of
  regenerating it. The memo is bounded by a byte budget (the oldest sources are dropped
  first and a source larger than the budget is not kept), so a structure that
  varies per request cannot grow it without limit. Set the environment variable
  `PURE_COMPILE_MEMO_BYTES` to change the budget (`0` disables the memo).
- `Compile::flush()` invalidates in-memory renderers (every shape recompiles on
  next use); it does not delete cache files.

`Compile::cachePath()` creates a missing directory with mode `0700`. It rejects a
mode that is writable by group or others; when the POSIX owner API is
available, it also rejects a directory owned by another user. The method does
not inspect whether the path is outside the web root, so deployments should
still keep the directory outside the document root and use a dedicated `0700`
(or stricter) directory. Do not point it at a shared location such as `/tmp`
itself. Delete the cache between deploys only if you want to force regeneration.

## Per-Request Guard

Compiling a shape per request is slower than rendering a compiled one. Enable
the development guard to detect it, and to surface the other problems that do
not show up in the output:

```php
<?php

use Pure\Compile\Compile;

Compile::guard(true); // or PURE_COMPILE_GUARD=1
```

- When the same call site calls `Compile::shape()` 20 times in one process (the
  20th call warns), an `E_USER_WARNING` suggests the `static $shape ??=` pattern.
- Data keys the rendered template never reads are reported with a `did you
  mean` suggestion, so a misspelled binding fails visibly instead of rendering
  as if the value were absent.
- An attribute setter whose name is one edit away from a standard attribute
  (`->clas(...)`, `->hreff(...)`) warns instead of silently creating a custom
  attribute. Callers that build custom attributes on purpose can ignore it.

Every warning fires once per subject per process. With the guard off (the
default), the checks cost one property read per render.

## Errors

- Missing required slot: `Pure\Core\MissingSlotException` with the full path,
  for example `slot 'items[].title' is required but was not provided.` When the
  scope holds other keys, the message suggests the closest one (a typo) or lists
  them. A required value or raw slot bound to an explicit `null` fails with
  `slot 'items[].title' is required but was null.`; rendering through a
  component call prefixes the component name or template path
  (`component 'Card': slot 'title' is required ...`).
- Wrong placement (raw slot as an attribute value)
  or a missing shape: `LogicException` at compile time.
- Non-iterable list, non-array item or scope, non-stringable value:
  `InvalidArgumentException` at render time (and `required()`/`default()` on
  `Slot::if()` throw a `LogicException`).

## Trees with Slots Cannot Use Other Output Paths

`Tag::render()`, `print()` and `save()` throw a `LogicException` for trees that
contain slots, because there is no data to bind. `toJSON()` describes slots as
`['slot' => 'name']`.

## Performance

The [canonical performance snapshot](/guide/compiled#performance) is the single
recorded reference for renderer, artifact, cache and page-level costs. It links
the exact benchmark commit, machine and recording date. Absolute results move
with the PHP version, opcache and CPU, so reproduce the relevant path with the
[benchmark source](https://github.com/YonLD/purephp/tree/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench)
before comparing:

```bash
php bench/compare.php
php examples/bootstrap/bench.php
```

## Trusted Markup and Artifacts

`Slot::raw()`, `Raw::of()` and a component's `Markup` children bypass output
escaping. They are trust boundaries, not sanitizers: only pass markup produced
or reviewed by your application, validate URL schemes and event/style
attributes, and keep dynamic tag names on an allowlist. A browser Content
Security Policy is still required when a page intentionally contains scripts,
styles or other active content.

A `*.pure.php` artifact is executable PHP generated from a trusted source tree.
Build and verify it in CI, restrict who can write it, and do not serve it as a
public static file or accept an artifact upload from an untrusted user. The
artifact's escaping contract does not make untrusted source or untrusted `Raw`
markup safe.

## Limitations

- Tag names cannot depend on data: a shape always uses the same tags. Use
  `Slot::if()` for structural variation, or normalize the data before
  rendering.
- Shapes only persist for the lifetime of a PHP process. In a long-running
  worker they can be reused across requests; `opcache.preload` does not retain
  their static state between requests. Under standard PHP-FPM the shape tree is
  rebuilt and the renderer regenerated on every request, which is slower than
  `Tag::render()`. Enable `cachePath()` so requests load the generated renderer
  instead of regenerating it.
- Compiled renderers trade compilation for speed: compiling a shape that is
  rendered once per process is slower than `Tag::render()`. Compile pages and
  components that are rendered repeatedly.
