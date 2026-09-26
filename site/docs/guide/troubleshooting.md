---
title: Troubleshooting
description: Symptom-based fixes for installation, slots, components, artifacts, caching, and examples.
---

# Troubleshooting

Start with the symptom that matches what you see. The examples on this page assume
that the project root is the current working directory and that Composer is installed.

## Installation and namespace errors

### `Class "Pure\..." not found` or `Call to undefined function Pure\...()`

Load Composer's autoloader before using the library, and check the PHP runtime
floor declared by the project:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{div, h1};

echo div(h1('Hello'))->render();
```

Use PHP 8.1 or newer. `use function` is required for helpers such as `div()`;
classes such as `Pure\Core\Slot` and `Pure\Component\Call` use `use`. If a copied
example omits the autoloader, it is not a standalone script.

### A command cannot find `vendor/bin/pure`

Install dependencies with `composer install`, then invoke the binary from the
project root:

```bash
composer install
vendor/bin/pure --help
```

The examples also use the root `vendor/autoload.php`; running a file from another
working directory does not change that requirement.

## Slot and binding errors

### `MissingSlotException: slot '...' is required but was not provided`

The template reads a required key, but the render data does not contain it. Add
the key at the call site, or mark the slot optional when the omission is valid:

```php
Slot::value('title')
Slot::value('title')->required(false)
Slot::value('title')->default('Untitled')
```

A required value or raw slot also rejects an explicit `null`. Pass the intended
text or a different, optional slot instead of using `null` to mean “missing”.
See the canonical [Compile API](/api/compile#slot-types) and
[Props and Slots](/guide/props).

### A child or each-slot value is rejected

`Slot::child()` and `Slot::each()` have structural contracts: a child scope is
an array, and every item in an each scope is an array. Normalize records before
rendering:

```php
$rows = array_map(
    static fn (array $record): array => ['title' => $record['title']],
    $records
);

echo $page(['rows' => $rows]);
```

A list of already-rendered strings belongs in `Slot::raw()`, not in
`Slot::each()`.

### `unknown prop`, `missing prop`, or a `did you mean` message

With a `prepare()` hook, the hook's parameters are the complete fluent prop
contract. Pass every required parameter by its exact name and remove unknown
setters:

```php
register(Card(...),
    factory: static fn () => div(...),
    prepare: static function (string $title, string $class = 'card'): array {
        return ['title' => $title, 'class' => $class];
    }
);

echo Card()->title('Welcome')->class('card');
```

Without `prepare()`, the setter names themselves are the bindings. Run
`vendor/bin/pure check components` after changing a unit to catch the mismatch
at the source.

### Children disappear or the call says the template has no `children` slot

Children are passed to the call, not through a `children()` setter. The template
must read the reserved slot explicitly:

```php
function Button(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Button(...), static fn () =>
    button(Slot::raw('children'))->class('btn')
);

echo Button('Save');
```

A template without `Slot::raw('children')` rejects non-empty children by design.

## Escaping and markup

### Markup is shown as escaped text

A plain string child is text. Pass a `Pure\Core\Markup` value (for example
`Raw::of($trustedHtml)` or another component `Call`) through a raw slot when it
is intentionally markup:

```php
use Pure\Core\Raw;

return ['body' => Raw::of($trustedHtml)];
```

Use `Slot::value()` for user text. Never treat request data as trusted markup
just because it came from a `#[Trusted]` parameter. The attribute documents a
trust boundary; it does not sanitize input.

## Artifacts, cache, and performance

### The output is old, or an artifact asks you to run `pure compile`

Rebuild artifacts after changing a source unit or upgrading the library:

```bash
vendor/bin/pure compile components
vendor/bin/pure compile --plain components
vendor/bin/pure compile --check --plain components
```

An artifact must be at least as new as its source and return a
`Pure\Compile\Renderer`. If a cache entry is stale, regenerate it with
`Compile::clearCache()` or remove only the library-owned cache directory. Do
not hand-edit generated files.

### A shape is rebuilt on every request

A `static` variable only lives for the lifetime of one PHP process. Under
PHP-FPM, enable the on-disk renderer cache once during bootstrap or ship fresh
artifacts:

```php
use Pure\Compile\Compile;

Compile::cachePath(__DIR__ . '/var/cache/purephp');
```

Keep the directory private and outside the web root. `Compile::flush()` clears
in-memory renderers; it does not delete cache files. See
[Compiled Rendering](/guide/compiled) and [Artifacts & Deployment](/guide/artifacts).

### `pure compile --list` shows an unexpected unit kind

The list describes discovered files, not a VitePress page:

- `(component)` is a registered `*.cmp.php` unit;
- `(shape)` is a standalone `*.shape.php` template;
- `(template)` is a function marked with `#[Template]`.

There is no `(page)` label. A page is a component unit whose root is a document
root, or a standalone template rendered by the caller.

### A plain view is missing or still calls PurePHP

Generate it explicitly:

```bash
vendor/bin/pure compile --plain examples/bootstrap
```

The `*.plain.php` view is markup and native PHP. The loader must extract the
bindings into locals before requiring it; see [Examples](/guide/examples) and
[Artifacts & Deployment](/guide/artifacts#plain-view-caveats).

## Component and PHPStan boundaries

### `unknown component 'X'` from PHPStan

`Pure\StaticAnalysis\UnknownComponentRule` compares literal component calls
with registrations in a full-project analysis. Register the unit with
`register(Icon(...))` in the analysed project, or use the same name that the
call function registers. Paths and `component(__FUNCTION__)` are resolved by
their file/function context.

Run the repository's configured analysis with:

```bash
composer phpstan
```

The rule is intentionally a project-wide check; single-file analysis cannot see
all registrations. `pure check` also loads discovered unit files and invokes
their factories to validate contracts, so run it only for trusted project code
and keep factories free of side effects.

### `Registry` is marked `@internal`

`Pure\Component\Registry` is the implementation registry used by the public
`register()` and `component()` functions. Application code should use those
helpers rather than depend on `Registry` method signatures. The public
component surface is summarized in [Component API](/api/component).

## HTTP and examples

### An HTMX response contains the complete page before the fragment

An HTMX endpoint should return only the fragment requested by the client. Branch
on the request method and emit the replacement markup once; do not print a full
document and then append a fragment. Keep the example's `/pure` and `/plain`
routes separate when comparing the two rendering paths.

### The local example returns 404

Start each example from its documented directory and use the matching front
controller:

```bash
php -S localhost:8000 -t examples/bootstrap/public examples/bootstrap/public/index.php
php -S localhost:8000 -t examples/event-counter/public examples/event-counter/public/index.php
php -S localhost:8000 -t examples/xml/public examples/xml/public/index.php
```

The route maps are documented on the [Examples](/guide/examples) page. A 404
response also lists the routes that the selected example supports.

## A short diagnostic checklist

1. Confirm `vendor/autoload.php` is loaded and PHP is 8.1 or newer.
2. Run `vendor/bin/pure compile --list <path>` and identify the actual unit kind.
3. Run `vendor/bin/pure check <path>` before debugging generated output.
4. Rebuild `*.pure.php` and `*.plain.php` artifacts after a source or dependency change.
5. Compare the data keys with the template's required slots; explicit `null` is not a substitute for a required value.
6. Use the smallest reproduction from [Examples](/guide/examples), then add one feature at a time.
