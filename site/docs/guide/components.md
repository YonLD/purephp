# Components

**Prerequisites**: [Props and Slots](/guide/props); **On this page**: wrapping shapes with components — call functions, templates and typed props.

A component wraps a shape: one file holds a PHP function that returns a
`Pure\Component\Call`, next to the template it renders (a shape) and the typed
props it accepts. The file registers a lazy factory, so `pure compile` can
precompile the template while a request only loads the artifact.

A unit file is a PHP source file: its imports and call function live in the same
file. The examples below assume the application has loaded `vendor/autoload.php`
in its front controller; the first one includes the autoloader and a CLI guard,
so the same file can also run as a standalone smoke test, as in
[Getting Started](/guide/getting-started).

## Your First Component

```php [components/Card.cmp.php]
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// the component unit: call function + template
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(): Call
{
    return component(__FUNCTION__);
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

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    echo Card()->title('Title')->content('Content');
}
```

- `register(Card(...))` derives the name and the file from the call function
  and stores the factory; it builds nothing. A request
  that has a fresh artifact never calls the factory.
- `prepare()` is the typed prop contract: its parameters are the props, PHP
  enforces their types, and the array it returns is what binds the template.
- `Card()` returns a `Call`; props are set like tag attributes and the markup is
  produced on string conversion.
- Run `vendor/bin/pure compile components` to build `Card.pure.php` and (with
  `--plain`) `Card.plain.php` next to the unit. `pure compile --list` prints
  `Card -> components/Card.cmp.php (component)` for this unit, `file (shape)`
  for a standalone shape, and `name -> file (template)` for a `#[Template]`
  builder.

The registered name and the path of the unit file are interchangeable:
`component(__DIR__ . '/Card.cmp.php')` resolves to the same binder, so a
component can be called by name or by file.

## Props

Props are the parameters of the unit's `prepare()` hook: type them, give them
defaults, and return them into the template's slots. Values that never change
can be baked into the template; anything that changes per render belongs in the
bindings.

```php [components/Badge.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\span;

function Badge(): Call
{
    return component(__FUNCTION__);
}

register(Badge(...),
    factory: static fn () => span(Slot::value('label'))->class(Slot::value('class')),
    prepare: static function (string $label, string $class = 'badge'): array {
        return ['label' => $label, 'class' => $class];
    }
);

Badge()->label('Save')->class('badge');
```

## Fluent Calls

A component call reads like a tag: props are set with the same fluent setters,
children are passed to the call when the unit declares them, and the result
nests wherever a tag does.

```php [components/PricingCard.cmp.php]
<?php

// a unit that also wraps the markup the call passes
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button, div, h2, li, ul};

function PricingCard(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(PricingCard(...),
    factory: static fn () => div(
        Slot::raw('children'),
        h2(Slot::value('type'))->class('card-title'),
        ul(Slot::each('features', li(Slot::value('value')))),
        button(Slot::value('text'))->class(Slot::value('class'))
    )->class('card'),
    prepare: static function (string $type, array $features, string $text, string $class): array {
        return ['type' => $type, 'features' => $features, 'text' => $text, 'class' => $class];
    }
);

echo div(
    PricingCard(h2('Pro'))
        ->type('Free')
        ->features([['value' => '10 users'], ['value' => '2 GB']])
        ->text('Sign up for free')
        ->class('btn btn-lg btn-block btn-outline-primary')
);
```

- `component($name, ...$children)` returns a `Pure\Component\Call`, which
  implements `Pure\Core\Markup`: `div(PricingCard(...))` emits it verbatim and
  renders it lazily with the tree, exactly like a tag child.
- Props bind slot names, so the template reads them with `Slot::value()`,
  `Slot::each()` or `Slot::child()`. `class()` and `style()` join their
  arguments exactly like the tag setters. A `null` prop leaves the prop unset
  (the slot then reports itself as not provided, or falls back to its default).
  A later `null` does not clear a value set by an earlier setter; choose the
  final value before setting it. A generic `Call` prop stores `false` as data,
  so the template's Slot decides whether it becomes text or an attribute; the
  special `class()` and `style()` setters keep their tag-style joining rules.
- `Call::props(array $props): self` sets several named props at once. It is the
  public batch form; do not instantiate `Call` directly because its constructor
  is internal.
For reference, the chainable public surface is:

```php
__call(string $prop, array $args): self
class(array|bool|int|float|string|null ...$values): self
style(string|array|null $value): self
props(array $props): self
```

`props()` follows the same value rules as the setters: `null` leaves a prop
unset and a `Slot` is rejected; use `class()` or `style()` when joining values
needs their special behavior.

- Children bind the reserved `children` slot: read it with
  `Slot::raw('children')`. A childless call renders it empty. The two mistakes
  fail differently — a call function that forwards children to a template
  without that slot throws on render, and a call function that takes none drops
  them without a warning — so the declaration follows the template: a unit whose
  template reads that slot declares `mixed ...$children` and forwards it to
  `component()`, and every other unit writes
  `function Card(): Call { return component(__FUNCTION__); }`, a plain prop chain.
- A prop the template does not read is reported by the development guard with a
  `did you mean` suggestion, and by `pure check` statically.

### Typed Props with prepare()

The fluent form passes props as data, so their types live in a `prepare`
closure instead of the call function. Its parameters are the prop contract —
PHP enforces the types, and a missing or unknown prop fails before rendering —
and the array it returns is what binds the template:

```php
function Section(): Call
{
    return component(__FUNCTION__);
}

register(Section(...),
    factory: static fn () => div(...), // the template; the bindings below are its slots
    prepare: static function (string $section, string $class, callable $item): array {
        $data = FeaturesService::section($section);

        return [
            'title' => $data['title'],
            'contents' => array_map(static fn (array $record): string => $item(...$record), $data['items']),
            'class' => $class,
        ];
    }
);

Section()->section('columns')->class('row g-4')->item(IconColumn(...));
```

Without a `prepare` closure the props are the bindings as they are, which fits
pure templates. `pure check` compares the `prepare()` parameters and the keys it
returns against the template's slots.

### Declared Props

A signature cannot state everything: which binding a prop fills when the names
differ, the shape of a list prop's items, or that a prop is on its way out. A
`#[Prop]` attribute states those facts, and `pure check` verifies them against
the template instead of inferring them:

```php
use Pure\Component\Prop;

use function Pure\HTML\div;

register(Card(...),
    factory: static fn () => div(\Pure\Core\Slot::value('title')),
    prepare: static function (
        #[Prop(slot: 'title')] string $text,
        #[Prop(item: 'value')] array $features,
        #[Prop(required: false)] ?string $class = null,
        #[Prop(deprecated: 'use class()')] ?string $style = null,
    ): array {
        return ['title' => $text, 'features' => $features, 'class' => $class, 'style' => $style];
    }
);
```

- `slot` names the binding the prop fills; the parameter name is the default.
  When `prepare()` does not return one readable array literal — it builds the
  array in steps, or merges one — the declared slots are what the required slots
  of the template are checked against, instead of the checker going quiet.
- `item` names the single slot each item of a list prop fills in the item shape
  of a `Slot::each` slot, so the checker compares the two.
- `required` states the caller obligation; a declaration that contradicts the
  signature is reported.
- `deprecated` carries a migration hint: `pure check` prints it for every call
  site binding the prop, and the development guard warns at the call itself.

`#[Trusted]` marks a prop that carries already-rendered markup, so `pure check`
verifies it binds a raw slot — markup bound to a text slot would be escaped —
and the development guard warns when a call passes a value that is not
`Pure\Core\Markup`, which is where untrusted input reaches the output:

The hook is a fragment inside `register()` rather than a standalone file:

```php
prepare: static function (#[Trusted] Markup $icon): array
{
    return ['icon' => $icon];
}
```

`#[Binds]` declares the keys of a `prepare()` that builds its bindings in steps
or merges them from a service, so the required slots stay checked when the
returned array cannot be read:

The same applies to a dynamically assembled binding set:

```php
prepare: #[Binds('title', 'desc')] static function (): array
{
    return PricingService::pricing();
}
```

A page unit whose hook returns a `...bindings()` helper result declares the
keys the same way, on the hook itself:
`prepare: #[Binds('header', 'pricing')] static fn (): array => pricingBindings()`.
When a list prop is bound to an array literal at the call site, its item keys
are compared with the item shape of the slot — `->links([['txet' => '...']])` is
reported where it is written. An item shape that reads several slots needs no
declaration of its own: the nested shape is the contract.

Declarations are read by `pure check` and by the development guard; they are
never consulted while rendering, and a unit without them behaves exactly as
before.

A component call adds the call object, prop setters and `prepare()` invocation
to direct rendering of a compiled tree. See
[Performance](/guide/compiled#performance) for what is paid once and what is
paid per request.

## Composing Components

A component that wraps markup reads it from a raw `children` slot, and the
caller passes the children to the call — exactly like a tag:

```php [components/Button.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Raw;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\button as htmlButton;

function Button(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Button(...), static fn () =>
    htmlButton(Slot::raw('icon'), Slot::value('label'), Slot::raw('children'))->class('btn')
);

Button(Raw::of('<span>+</span>'))->label('Add');
```

Lists work the same way: build the list of child calls (or rendered strings) in
the component's `prepare()` or at the call site and pass it into a raw slot — it
is stringified element by element and concatenated, so there is no `implode()`
to remember. Use `Slot::each()` inside
the template when the items are plain data rows that need no per-item component
logic.

## Pages

A page is a component unit whose root tag is a document root (`html`, `svg`,
`xml`, …). There is no separate page API: register it with `register()`, let its
`prepare()` hook supply the blocks, render it with `component()`, and pass the
call to `renderHTML()` / `renderXML()`, which prepend the document header of the
function's flavour — `<!DOCTYPE html>` for `renderHTML()`, the XML declaration
for `renderXML()`:

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
        // The page decides which blocks exist; each block fetches its own records.
        'title' => FeaturesService::pageTitle(),
        'content' => FeaturesBody(),
    ]
);

function featuresPage(): string
{
    return renderHTML(component('Features'));
}
```

The call emits the tree as written, without a header; `renderHTML()` /
`renderXML()` add the header of the function's flavour, so a full page is that
header plus the rendered fragment.

`pure compile --plain` writes the same page as a dependency-free view file, so a
deployment without purephp can serve it; the controller prints the same
bindings either way.

## The Public Component Boundary

Use the named public helpers in application code:

- `register(Card(...), $factory)` registers a unit; the name and file derive
  from the call function.
- `component(string $name, mixed ...$children): Call` starts a call for a
  registered name or a `*.cmp.php` / `*.shape.php` path.
- `Call::props(array $props): self` sets several named props at once.

`Pure\Component\Registry` is class-level `@internal`. Its binder, cache and
registration methods may be used by the implementation, but their signatures
are not an application compatibility promise. If an advanced integration needs
a lower-level binder, isolate it behind an adapter instead of making it part of
an application contract.

For an inline tree, compile it once and keep the shape:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

function Tag(string $label): string
{
    static $render;
    $render ??= Compile::shape(div(Slot::value('label'))->class('tag'));

    return $render(['label' => $label]);
}
```

The component path caches its internal binder per name or path, so a registered
unit does not need a user-written `static` variable.

## Caching

- A unit is served by its `*.pure.php` artifact when it is at least as new as
  the unit file; the factory and the shape tree are then never touched.
- The internal component binder caches per name or path for the compile
  generation; application code should use `component()` rather than depend on
  `Registry` directly.
- `Compile::cachePath($dir)` — requests load generated renderers instead of
  regenerating them.
- `pure compile --check` keeps artifacts fresh in CI; a long-running worker
  keeps the loaded renderer in memory, so artifacts are optional there.

A component call adds a little work to rendering a compiled tree directly: the
call object, the prop setters and the `prepare()` invocation. With artifacts and
opcache, loading a component is a single `require`, so that combination is the
production path.

## Immediate Rendering (Snippets)

For one-off fragments you can skip shapes entirely and render a tag tree
directly:

```php
<?php

use function Pure\HTML\{div, h2, p};

div(h2('Title'), p('Content'))->class('card')->print();
```

Use this for snippets and debugging only; production components should compile
a template so escaping and structure costs are paid once.

## Trusted Markup and CSP

`#[Trusted]` and `Pure\Core\Markup` describe a trust boundary; they do not
sanitize input. Pass only markup produced or reviewed by your application to a
raw slot, validate URL schemes and event/style attributes, and restrict dynamic
tag names. Configure a browser Content Security Policy for pages that include
scripts or other active markup. Precompiled `*.pure.php` artifacts are
executable PHP and should be built from trusted source with restricted write
access.

## Slot Reference

Components are functions; slots are the vocabulary *inside* a template:
`Slot::value()`, `Slot::raw()`, `Slot::child()`, `Slot::each()` and
`Slot::if()`. See [Props and Slots](/guide/props) for
the complete binding reference.

## Next Steps

- [Compiled Rendering](/guide/compiled) - How a component's template compiles and caches
- [Artifacts & Deployment](/guide/artifacts) - Artifacts, caching and plain views
- [Props and Slots](/guide/props) - The complete data-binding reference
- [Events](/guide/events) - Event attributes and browser-side handlers
