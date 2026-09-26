# Artifacts & Deployment

**Prerequisites**: [Compiled Rendering](/guide/compiled), [Components](/guide/components); **On this page**: `pure compile` artifacts, the `pure check` contract check, plain views and deployment caching.

The disk cache still rebuilds the shape tree on every request. To deploy without building shapes at all, compile them ahead of time with the `pure` command.

## Precompiled Artifacts

```bash
vendor/bin/pure compile src
```

Every `*.cmp.php` unit is compiled into a sibling `*.pure.php` artifact. An
artifact declares the shape fingerprint and returns a `Renderer`, so it needs
neither the shape tree nor the compile cache:

```php
<?php

$page = require __DIR__ . '/page.pure.php';

echo $page->render(['title' => 'Users']);
$page->save(__DIR__ . '/out.html', ['title' => 'Users']);
```

::: tip Advanced: standalone `*.shape.php` templates
Besides units, `pure compile` also discovers `*.shape.php` files that return a
`Shape` — a template with no call function. Components are the recommended
form; reach for a shape file only when there is no component to own the
template.
:::

- `pure compile <path>...` accepts files and directories (searched recursively),
  discovers both `*.cmp.php` units and `*.shape.php` templates, and skips the
  files whose content is already current: the shape is still loaded and compiled
  (so a change in anything it pulls in is picked up), but an up-to-date file is
  reported as `unchanged:` instead of rewritten. `--list` prints the exact
  labels `name -> file (component)`, `file (shape)`, and
  `name -> file (template)` for builders marked with `#[Template]`; it does not
  compile. `pure compile --check` writes nothing and exits with code 1 when an
  artifact is stale or missing, which fits a CI step. `--plain` also writes the
  dependency-free view described below, and `--check --plain` covers both
  flavors. The repository examples ship `*.cmp.php` units, so
  `vendor/bin/pure compile examples` compiles them all. A typical `--list` line
  is `Card -> components/Card.cmp.php (component)`; a standalone template is
  reported as `views/page.shape.php (shape)`, and a `#[Template]` builder as
  `pageShape -> views/page.cmp.php (template)`.
- Artifacts render the same output as the runtime compiler (asserted byte for
  byte by the tests) and read as a template: markup stays markup, values become
  `<?= ... ?>`, control flow uses the alternative syntax, and the closure is
  defined once with imported short class names. HTML runs keep the exact
  rendered bytes, so they are never re-indented. `Renderer::$source` is empty
  for artifacts — the file itself is the source.
- Dynamic values read their slot through `TemplateRuntime`, which keeps the
  compiled semantics in one place: a required slot throws
  `MissingSlotException` (an explicit `null` fails a required value or raw slot,
  an attribute slot keeps omitting itself), `default:` supplies the compiled
  default of an optional slot, and values are escaped or coerced exactly like the
  flat renderer does. `path:` only appears where the slot path differs from the key.
- An artifact also carries the root slot manifest (`Renderer::$slots`), so the
  development guard can report bindings the template never reads without
  rebuilding the shape tree.

The generated body is illustrative source, not an application API. Its
`TemplateRuntime` import is emitted by `pure compile`; application code should
continue to call `Renderer::render()`:

```php
<?php

$pureBody = static function (array $v): string {
    ob_start();
    try { ?><div class="card"><h1><?= TemplateRuntime::text($v, 'title') ?></h1><ul><?php
        foreach (TemplateRuntime::items($v, 'items') as $item1):
            $v2 = TemplateRuntime::scope($item1, 'items[]'); ?><li><?= TemplateRuntime::text($v2, 'label', path: 'items[].label') ?></li><?php
        endforeach; ?></ul></div><?php
    } finally {
        $out = (string)ob_get_clean();
    }

    return $out;
};
```

There is no automatic document header: the tree renders as written, and a full
document's header (`<!DOCTYPE html>` for an HTML root, the XML declaration for an
XML or SVG one) is the caller's to prepend — `renderHTML()` / `renderXML()` do it
for a tag tree or a component call, and the root tag's `documentHeader()` gives
the header when a `Renderer` is rendered directly. Components in
`examples/bootstrap` are units built on that:

```php [components/Icon.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\SVG\{svg, svgUse};

function Icon(): Call
{
    return component(__FUNCTION__);
}

register(Icon(...),
    factory: static fn () =>
        svg(svgUse()->href(Slot::value('href')))->class(Slot::value('class')),
    prepare: static function (string $href, string $class = 'bi'): array {
        return ['href' => $href, 'class' => $class];
    }
);
```

```php [views/features.cmp.php]
<?php

use Pure\Component\Binds;
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{body, head, html, title};
use function Pure\Utils\renderHTML;

function Features(): Call
{
    return component(__FUNCTION__);
}

register(Features(...),
    factory: static fn () =>
        html(
            head(title(Slot::value('title'))),
            body(Slot::raw('content'))
        ),
    prepare: #[Binds('title', 'content')] static fn (): array => [
        'title' => FeaturesService::pageTitle(),
        'content' => FeaturesBody(),
    ]
);

function featuresPage(): string
{
    // renderHTML() prepends the document header; the tree renders without one.
    return renderHTML(component('Features'));
}
```

Each block fetches its own records from the service layer in the bootstrap
example, so the page function carries no page data and adding a prop to a
component never touches the page.

A child component's markup goes straight into a raw slot — no `(string)` cast —
and a list of them is concatenated in order.

The public `component()` helper resolves a unit or shape file and loads its
artifact when one exists next to it and is at least as new as the file;
otherwise it calls the registered factory (once per compile generation) or
compiles the shape file (the disk cache still applies). A call returns the
fragment only — the document header, if you want one, is the caller's to
prepend. The lower-level `Registry::component()` binder is an internal detail;
application code should not depend on its signature.

The bootstrap example's `PlainFeaturesController`
passes the same bindings through the example's
`plain()` helper (an app function: it requires the view file and extracts the
data), and one router (`public/index.php`) serves every page in both flavors —
`/pure/features` and `/pure/pricing` render the page functions while
`/plain/features` and `/plain/pricing` render the plain views — so you can
compare the flavors while developing.

- Build artifacts with the same PHP minor version as production:
  `Shape::id()` salts its SHA-1 input with
  `PHP_MAJOR_VERSION.PHP_MINOR_VERSION`, and the artifact header records the
  PHP version. Do not copy artifacts or cache files across PHP minor versions.
- Artifacts are build output: rebuild them after changing a shape. Loading does
  not verify the shape tree, so `--check` is the way to notice a stale artifact.
- An artifact also carries its `Compile::CACHE_VERSION`: loading one written by
  another version of the library throws with a `pure compile` message instead of
  failing on the `Renderer` signature it describes. A version bump invalidates
  the `Compile::cachePath()` renderers on its own, never the artifacts beside
  your templates, so `pure compile` is part of an upgrade. `*.plain.php` views
  carry the version in a comment but have no executable guard, so they silently
  serve stale output until `pure compile --check --plain` catches the mismatch.
- Freshness is compared with `filemtime()`, whose whole-second granularity means
  an artifact written in the same second as its unit already serves it. This is
  deliberate: `touch`-style skew from a tar, rsync or git checkout is common,
  and an exact comparison would discard those artifacts and recompile them per
  request. The recorded hash-versus-require comparison is in the
  [canonical performance snapshot](/guide/compiled#performance); commands and
  source are in the [benchmark repository snapshot](https://github.com/YonLD/purephp/tree/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench).
- Two source files that would write the same artifact (`a.shape.php` beside
  `a.cmp.php`) are both rejected by `pure compile` with exit code 1, so
  discovery order cannot decide which template owns `a.pure.php`.
- Output echoed while a shape file loads is discarded; build messages are the
  only thing `pure compile` writes.

## Contract Check

`pure check` validates the contract of every unit, so a mismatch fails in CI
instead of at render time. It is a contract check, not a sandbox: to inspect a
unit it `require`s the file and invokes its registered factory (when present) to
build its shape. Run it only on trusted source in an isolated CI process; it does not
write artifacts.

```bash
vendor/bin/pure check src
```

- The **slots** a template reads against the **bindings** its unit returns: a
  binding the template does not read is an error (with a `did you mean`
  suggestion), and a required slot the hook does not bind is an error. A
  returned bindings array is resolved when it is a single array literal — or a
  `...bindings()` helper result declared with `#[Binds]` — and reported as
  `info` when it is computed at runtime.
- The **parameter types** of the `prepare()` hook against the slot kinds: a
  list slot needs an iterable, a child scope an array, a text slot a stringable,
  a raw slot either. A nullable parameter for a required slot is a warning
  (binding `null` throws `MissingSlotException`), as is a parameter that is
  neither used in the hook nor a slot of the template.
- A unit without `prepare()` passes its props straight to the bindings, so it is
  checked at its **call sites** instead: the setters must match the template's
  slots, and a function next to the unit that does not return
  `Pure\Component\Call` is an error.
- **Declarations** state what a signature cannot. `#[Prop]` carries `slot`
  (the binding the prop fills), `item` (the single slot each item of a list prop
  fills in a `Slot::each` item shape), `required` (the caller obligation) and
  `deprecated` (a migration hint); `#[Trusted]` marks a prop that carries
  markup, so it must bind a raw slot and the development guard warns when a call
  passes a value that is not `Pure\Core\Markup`; `#[Binds]` on a hook or a
  `...bindings()` helper declares the returned keys when its array literal
  cannot be read. Declarations are compared with the signature and the template,
  and when `prepare()` does not return one readable array literal they are what
  the required slots are checked against. A call site binding a deprecated prop
  is a warning.
- The **fluent calls** in every checked file: a `->prop(...)` the target does
  not accept is an error with a `did you mean`, `->children(...)` points at the
  call syntax instead, and a prop set unpacked from a variable is skipped. A
  list prop bound to an array literal is compared item by item with the item
  shape of its slot (a missing required key and an unread key are both errors).
  The target must be among the checked files for its props to be known.
- A slot name one template uses as both a scalar (value/raw) and a scope
  (child/each) is an error; `*.shape.php` templates are checked for that too.

Exit code 1 on errors, and on warnings with `--strict`. `pure check` does not
look at artifacts — `pure compile --check` is the freshness check.

## Component Artifacts and Caching

Every component is a `*.cmp.php` unit, so `pure compile` builds it like any
other template. The binder checks the artifact
mtime against the unit file: a fresh artifact is loaded as-is (no factory call,
no shape tree, no fingerprint), a stale or missing one calls the registered
factory or compiles the shape file. In CI, `pure compile --check` reports stale
artifacts with exit code 1.

`Compile::cachePath()` creates a missing cache directory with mode `0700` and
rejects group/other-writable modes. When the POSIX owner API is available it
also rejects a directory owned by another user, but it does not check that the
path is outside the web root. Keep the cache in a dedicated directory outside
the document root with restrictive permissions.

What to enable depends on the deployment:

- **PHP-FPM** — enable `Compile::cachePath()` and build artifacts. Without an
  artifact, every request rebuilds the component's shape tree and walks its
  fingerprint before rendering. An artifact replaces that work with one
  `require`; see the [canonical performance snapshot](/guide/compiled#performance)
  and [`bench/cache.php`](https://github.com/YonLD/purephp/blob/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench/cache.php)
  for the recorded comparison.
- **Long-running workers** (RoadRunner, Swoole, FrankenPHP) — enable
  `Compile::cachePath()`; the internal per-path binder cache and
  `static $render` for inline trees keep the renderer in memory, so artifacts
  are optional.
- **`opcache.preload`** — preloading keeps code in memory but does not carry
  static variables across requests (PHP's preload RFC states this explicitly),
  so it is not a substitute for either of the above.

With opcache, artifacts let production load compiled component code directly.
The recorded page-level comparison is in the
[canonical performance snapshot](/guide/compiled#performance); run
[`bench/registry.php`](https://github.com/YonLD/purephp/blob/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench/registry.php)
on your own deployment before choosing a cache layout.

## Dependency-Free Exports (Optional)

`pure compile --plain` writes a `*.plain.php` view: markup and native PHP that
runs without purephp installed. Load it by extracting data into locals. Keep the extraction in an isolated
closure so its variables cannot overwrite a caller's locals:

```php
<?php

$render = static function (array $viewData): string {
    extract($viewData, EXTR_SKIP);
    ob_start();

    try {
        require 'views/index.plain.php';
        $output = (string) ob_get_clean();
    } catch (\Throwable $error) {
        ob_end_clean();
        throw $error;
    }

    return $output;
};

$html = $render($data);
```

`EXTR_SKIP` does not overwrite an existing variable. A data key that collides
with a local such as `viewData` is skipped; in the older top-level pattern,
keys such as `data` or `html` can therefore collide with the loader itself.
Inspect the generated view and map such keys explicitly (or use
`EXTR_OVERWRITE` only inside a deliberately isolated, trusted scope).

Reach for `--plain` when views must run without the library — a deployment that
ships only `public/` and `views/`, or a template directory handed to someone else.
For ordinary data a plain view renders the artifact's bytes exactly, preceded by
the document header only when its root is a document root (`<html>` or an XML
tree): a page keeps its `<!DOCTYPE html>` / XML declaration, while a fragment
(a `div`, an inline SVG icon) starts with its markup, so including it never
injects a header into the middle of a document. It is also the fastest form:
values go straight into `htmlspecialchars()`, with no runtime accessor calls.
It is a plain view, not a compiled component, so the strict slot semantics stay
with the artifact. See [Plain View Caveats](#plain-view-caveats) for the
semantic differences (undefined variable on missing slot, empty string on null
attribute, no iterable check on lists, PHP coercion of raw elements).

## Plain View Caveats

A plain view is markup and native PHP — it renders without purephp installed,
but it does not carry the strict slot semantics of the compiled renderer:

- a missing required slot is an undefined variable, not `MissingSlotException`;
- a required slot bound to `null` renders empty instead of failing (the artifact
  rejects it for value and raw slots);
- the plain view of a document root starts with its `<!DOCTYPE html>` / XML
  declaration, while a fragment view starts with its markup;
- a `null` attribute prints an empty value instead of disappearing;
- list slots are not checked for being iterable, and values are stringified by
  PHP rather than by `SlotRuntime`;
- a raw slot joins an iterable of stringable values with `implode('')`, like the
  artifact; an element PHP cannot stringify is coerced with a warning (`Array`)
  instead of raising the artifact's `InvalidArgumentException`.

A plain view is an include: enable opcache in production, or every render parses
the file again.

A top-level slot reads as a plain variable and a nested slot reads as the array it
lives in, with escaping inlined, so a plain view stays as portable as a
hand-written template.

With components, the controller renders each component first and passes its
markup to the page shape as raw bindings, so the view file still needs no
library while the request handler does.

The view declares every root slot with an `@var` annotation derived from the
shape, so static analyzers read the extracted locals without an exclusion:

```php
<?php

/**
 * @var scalar|null|\Stringable $title
 * @var array{columns: ...} $content
 */
```

Value slots are `scalar|null|\Stringable`, condition slots are `mixed`, and
child/list scopes become array shapes and iterables of them. Odd slot names are
declared on the loader's `$data` array. Annotations are comments: they add no
output bytes.

## Trusted Artifacts and CSP

A `*.pure.php` artifact is executable PHP generated from a source tree. Treat
both the source and the generated artifact as trusted code: build them in a
controlled CI job, restrict write access, verify the build output, and never
serve an artifact as a public static file or accept one from an untrusted
user. A plain view is also generated code even though it does not require the
library at runtime.

`Raw::of()`, `Slot::raw()` and component `Markup` children bypass escaping.
They do not sanitize URLs, event handlers, styles or scripts. Validate those
inputs at the application boundary and send an appropriate browser Content
Security Policy for pages that intentionally contain active markup, for
example:

```http
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'
```

Adjust the policy to the application's trusted assets; CSP is defense in depth,
not a replacement for validation.

## Next Steps

- [Compiled Rendering](/guide/compiled) - Shape compilation, the runtime cache and limitations
- [Components](/guide/components) - Components wrap shapes
- [Compile API](/api/compile) - The `Compile`, `Shape` and `Renderer` classes
