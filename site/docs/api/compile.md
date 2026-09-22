# Compiled Rendering

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
| `Pure\Compile\Compile` | Facade: `shape()`, `cachePath()`, `clearCache()`, `flush()`, `guard()` |
| `Pure\Compile\Shape` | A data-free tree: `__invoke($data)`, `compile()`, `id()`, `print($data)`, `save($path, $data)` |
| `Pure\Compile\Renderer` | The compiled renderer: `render($data)`, `save($path, $data, $header = '')` and the readonly `source` / `id` / `slots` properties (`slots` is the root slot manifest the compiled renderer was built with) |
| `Pure\Core\Slot` | Placeholder constructors (`value`, `raw`, `child`, `each`, `if`) and modifiers |
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

| Function | Behavior |
| --- | --- |
| `register(Closure $call, ?Closure $factory = null, bool $override = false, ?Closure $prepare = null): void` | Registers a component unit. Pass the call function — `register(Card(...), $factory)` — and the name and file derive from it. The factory must be lazy and may return a tag tree or a `Shape`, and `$prepare` is the optional typed props-to-bindings hook of a fluent call |
| `component(string $name, mixed ...$children): Call` | Starts a fluent call: props are set like tag attributes, children bind the reserved `children` slot, and the result is `Markup`, so it nests like a tag; `$name` is a registered name or a template path, and a unit's own call function passes `__FUNCTION__` |
| `Registry::component(string $nameOrPath): Closure(array $data): string` | Returns the binder of a unit or shape file, to hold or pass around yourself |

There is no page flavour: to emit a full document, pass the tag tree or the
component call to `Pure\Utils\renderHTML()` / `renderXML()`, or prepend the
header yourself — the root tag's `documentHeader()`, or the
`HTML::DOCUMENT_HEADER` / `XML::DOCUMENT_HEADER` constants.

A fluent call binds one prop per setter (`Card($children)->title($title)`);
`null` leaves a prop unset, and children bind the reserved `children`
slot (`Slot::raw('children')`). To hold or pass around the binder yourself, use
`Registry::component($source)`, which returns a `Closure(array $data): string`
taking the slot values as an associative array. `Call` implements
`Pure\Core\Markup`, and so does `Raw`; a `Markup` child is emitted verbatim and renders lazily with the
tree, while every other child is frozen to text and escaped. A component call
cannot be part of a data-free shape — render it into a raw slot instead.

The source is a registered name, the path of a `*.cmp.php` unit or the path of
a `*.shape.php` template. Registering the same name for another file, or another
name for the same file, throws unless `override: true` is passed; one unit file
registers one component. A name and the path of its unit file resolve to the
same binder.

For a file, the sibling `*.pure.php` artifact is loaded when it exists and is at
least as new as the unit or shape file, so production skips calling the factory
and building the shape tree; otherwise the factory runs (once per compile
generation) or the shape file is compiled (the disk cache still applies).
Missing files, a template that does not return a tag tree or a `Shape` and an
artifact that does not return a `Renderer` all raise a `RuntimeException` naming
the file. Run `pure compile` to build artifacts for every `*.shape.php` and
`*.cmp.php` file, `pure compile --list` to print the units found,
`pure compile --check` to keep artifacts fresh in CI, and `pure check` to
validate the component contract (slots against bindings and parameter types)
without writing anything.

## Shape vs. Data

A shape is a normal tag tree in which dynamic values are replaced by `Slot`
placeholders. Shapes must not contain request data, and must be built **once
per process** — file-backed templates get that from the per-path binder cache
in `Registry::component()`, inline trees from a `static` variable inside the
function that builds them, never inside a request handler.

A child component's markup enters a template through a raw slot — a bare string
child would be escaped as text:

```php
<?php

$shape = Compile::shape(div(Slot::raw('header'), Slot::each('rows', $row))->class('page'));
$shape(['header' => Header(), 'rows' => $rows]);
```

## Slot Types

| Constructor | Value | Behavior |
| --- | --- | --- |
| `Slot::value($name)` | stringable or `null` | position decides the semantics: child position coerces to string and escapes (`null` renders as empty content, `true` as "1"); attribute position follows `setAttr()` (`true` renders `name="name"`, `false`/`null` omit the attribute) |
| `Slot::raw($name)` | stringable, `null`, or an iterable of those | emitted verbatim, never escaped; an iterable is concatenated |
| `Slot::child($name, $shape)` | array | nested data scope for `$shape` |
| `Slot::each($name, $shape)` | iterable of arrays | renders `$shape` for every item |
| `Slot::if($name, $then, $else = null)` | truthy check | renders `$then` when `$data[$name]` is truthy, otherwise `$else`; a missing key is false and never throws |

Modifiers:

- `->required(false)` — the slot may be missing.
- `->default($value)` — fallback used when the key is missing.
- `Slot::if()` rejects both modifiers with a `LogicException`.

Value and raw slots must be stringable: `null`, scalars, and `Stringable`
(including a `Raw`) are accepted; other objects raise an
`InvalidArgumentException` naming the full slot path. A `raw` slot additionally
accepts an iterable of stringable values and concatenates them verbatim; a
non-stringable element names its offset, e.g.
`slot 'items[2]' must be stringable`.

Pick a list slot by whether the markup is already rendered: `raw()` concatenates
markup that already exists (pass the rendered string, a `Raw`, or a list of
them); `each()` is data-driven and renders every item through its own shape.

## Static Subtree Folding

A subtree that contains no slots is static markup. The compiler folds it into a
single literal by rendering it once at compile time, so such subtrees cost
nothing at render time:

```php
<?php

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

`Shape::id()` is a sha1 fingerprint of the shape's structure: tag names,
attribute names and values, slot kinds and names, defaults, nested shapes and a
library cache version. It is computed without compiling, and it keys the
on-disk renderer cache: the generated source is stored under it, so two shapes
that differ anywhere in the structure cannot share a cached renderer. A
`*.pure.php` artifact records it in its header; `pure compile --check` recognises
a stale artifact by comparing the artifact with the freshly generated source byte
for byte. It is not what
`Registry::component()` resolves a component by — that is the registered name
or the unit file — and it does not change when only the *data* changes. Where it
does help is an application that assembles a different shape per variant: the
fingerprint is a cheap, stable key for the memo it keeps them in:

```php
<?php

$shapes[$classList . '|' . $item->id()] ??= Compile::shape(...);
```

## Renderer API

```php
<?php

$compiled = $shape->compile();

$compiled->render($data);                    // string
$compiled->save($path, $data);               // write to file, returns bytes written
$compiled->save($path, $data, $header);      // prepend $header to the file
$compiled->source;                           // generated PHP source (empty for precompiled artifacts)
$compiled->id;                               // structure fingerprint (same as Shape::id())
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

The directory must be private: owned by the PHP user and not writable by group
or others (`cachePath()` creates missing directories with 0700 and rejects
loose or foreign-owned ones), and it should live outside the web root. Do not
point it at a shared location such as `/tmp` itself. Delete the cache between
deploys only if you want to force regeneration.

## Per-Request Guard

Compiling a shape per request is slower than rendering a compiled one. Enable
the development guard to detect it, and to surface the other problems that do
not show up in the output:

```php
<?php

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

`bench/compare.php` measures the paths on a 604-element page, and
`examples/bootstrap/bench.php` measures a real page composed from component
functions; `bench/README.md` holds the recorded rows and [the compiled
guide](/guide/compiled#performance) explains which cost dominates when.
Absolute numbers move with the PHP version, opcache and the CPU, so run them
before comparing:

```bash
php bench/compare.php
php examples/bootstrap/bench.php
```

## Limitations

- Tag names cannot depend on data: a shape always uses the same tags. Use
  `Slot::if()` for structural variation, or normalize the data before
  rendering.
- Shapes only persist for the lifetime of a PHP process. In long-running
  workers (or with `opcache.preload`) that is once per worker; under standard
  PHP-FPM the shape tree is rebuilt and the renderer regenerated on every
  request, which is slower than `Tag::render()`. Enable `cachePath()` so
  requests load the generated renderer instead of regenerating it.
- Compiled renderers trade compilation for speed: compiling a shape that is
  rendered once per process is slower than `Tag::render()`. Compile pages and
  components that are rendered repeatedly.
