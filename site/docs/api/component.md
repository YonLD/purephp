---
title: Component API
description: Public PurePHP component helpers, Call behavior, registry boundaries, contract attributes, Template, and PHPStan integration.
---

# Component API

This page covers the component surface around `Pure\Component`. The canonical
`Slot` constructor and value table lives in
[Props and Slots](/guide/props#slot-reference); the guide explains composition,
while this page records the public entry points and their boundaries.

## Public surface at a glance

| Surface | Role | Stability |
| --- | --- | --- |
| `Pure\Component\component()` | Start a fluent `Call` for a registered name or unit/template path. | Public helper. |
| `Pure\Component\register()` | Register a named unit from its call function and lazy factory, with an optional `prepare()` hook. | Public helper. |
| `Pure\Component\Call` | Fluent props, children, rendering, and string conversion. | Public result type; construct it through `component()`. |
| `Pure\Component\Prop` | Declare a `prepare()` parameter's slot, item shape, requiredness, or deprecation. | `pure check` contract. |
| `Pure\Component\Trusted` | Mark a `prepare()` parameter that carries trusted markup. | `pure check` and development-guard declaration. |
| `Pure\Component\Binds` | Declare keys returned by a `prepare()` hook or bindings helper. | `pure check` contract. |
| `Pure\Compile\Template` | Mark a template builder beside a unit. | `pure compile` / `pure check` integration. |
| `Pure\StaticAnalysis\UnknownComponentRule` | Report literal component calls with no analysed registration. | Public PHPStan rule (`@api`). |
| `Pure\Component\Registry` | Internal registry used by the helpers and compiler. | `@internal`; do not build an application contract on it. |

## `component()` and `Call`

The public helper has this shape:

```php
function component(string $name, mixed ...$children): Call;
```

`$name` is either a registered component name, a `*.cmp.php` unit path, or a
`*.shape.php` template path. A unit's own call function should pass
`__FUNCTION__`, so its name is written only once. It declares children only when
its template reads the `children` slot; every other unit takes none:

```php
function Card(): Call
{
    return component(__FUNCTION__);
}
```

`Call` is a `Pure\Core\Markup` value. It can therefore be nested wherever a tag
child is accepted, and string conversion renders the unit lazily.

### Props and children

A prop is one named binding. The usual fluent form is:

```php
echo Card()
    ->title('Free')
    ->content('Everything you need');
```

- `__call($prop, $value)` sets one prop and returns the same call.
- `class(...$values)` and `style($value)` use the same joining behavior as tag
  setters.
- `props(array $values)` sets several props at once; keys are prop names.
- `null` leaves a prop unset, matching `Tag::setAttr()`.
- Passing a `Slot` as a prop is invalid; bind a value and read the slot in the
  template instead.
- Children are passed to the call, not through a `children()` setter. They bind
  the reserved `children` slot, which a template reads with
  `Slot::raw('children')`, so the declaration follows the template: forwarding
  children to a template without that slot throws on render, and a call
  function that takes none drops them silently.

A unit that renders its children declares them and forwards them to the call, and
its call takes them as arguments:

```php [components/Button.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button as htmlButton, span};

function Button(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Button(...), static fn () =>
    htmlButton(Slot::raw('children'), ' ', Slot::value('label'))->class('btn')
);

echo Button(span('Save'))->label('now');
```

Children follow the tag rules: a `Tag` or `Markup` child is verbatim markup, a
string child is escaped text, and a `Slot` child is rejected.

The class has `render(): string` and `__toString(): string`. The constructor is
marked internal; create calls with `component()` rather than instantiating
`Call` directly.

## Registering a unit

The public helper is:

```php
function register(
    Closure $call,
    ?Closure $factory = null,
    bool $override = false,
    ?Closure $prepare = null
): void;
```

The nullable type is part of the helper signature, but a component registration
must provide a non-null factory; `null` throws `InvalidArgumentException`.

The recommended call-function form derives the component name and unit file by
reflection:

```php
register(Card(...),
    factory: static fn () => div(
        h2(Slot::value('title')),
        p(Slot::value('content'))
    )->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);
```

The factory is lazy: registration stores it, and a fresh `*.pure.php` artifact
can serve the unit without calling it. The `prepare()` parameters are the typed
prop contract; its returned array binds the template. One unit file registers
one component. Replacing an existing name or file requires `override: true`.

A direct `Registry::register()` call can be recognized by the PHPStan collector,
but it is not the preferred application API. Prefer `register(Card(...), ...)`.

## Contract attributes

These attributes are read by `pure check` and the development guard. They do not
change the renderer and they do not sanitize input.

### `#[Prop]`

`#[Prop]` decorates a parameter of a `prepare()` hook:

```php
use Pure\Component\Prop;

prepare: static function (
    #[Prop(slot: 'title')] string $text,
    #[Prop(item: 'value')] array $features,
    #[Prop(required: false)] ?string $class = null,
    #[Prop(deprecated: 'use class()')] ?string $style = null,
): array {
    return [
        'title' => $text,
        'features' => $features,
        'class' => $class,
        'style' => $style,
    ];
}
```

- `slot` names the binding filled by the prop; it defaults to the parameter name.
- `item` names the slot filled by each item of a list prop.
- `required` states the caller's obligation; a contradiction with the signature
  is reported.
- `deprecated` is a migration hint shown for call sites that bind the prop.

When `prepare()` does not return one readable array literal, the declared slots
remain available to the checker instead of making required-slot checks silently
disappear.

### `#[Trusted]`

`#[Trusted]` marks a prop that carries already-rendered markup:

```php
use Pure\Core\Markup;
use Pure\Component\Trusted;

prepare: static function (#[Trusted] Markup $icon): array
{
    return ['icon' => $icon];
}
```

`pure check` verifies that the prop binds a raw slot and is not also read as a
text slot. The development guard warns when the call value is not
`Pure\Core\Markup`. This is a trust declaration, not an escaping function: pass
only markup you have already established as trusted.

If the prop name differs from its slot name, combine it with
`#[Prop(slot: 'icon')]`.

### `#[Binds]`

`#[Binds]` declares the keys returned by a hook or helper when the returned
array is assembled dynamically:

```php
use Pure\Component\Binds;

prepare: #[Binds('title', 'description')] static function (): array
{
    return PricingService::pricing();
}
```

The attribute can also target a bindings helper. The checker compares its keys
with the template, so a missing required key or an undeclared key does not hide
behind a service call. It is never consulted during rendering.

## `#[Template]`

`Pure\Compile\Template` marks a shape builder in a unit file:

```php
use Pure\Compile\Compile;
use Pure\Compile\Shape;
use Pure\Compile\Template;
use Pure\Core\Slot;

use function Pure\HTML\{div, h2};

#[Template]
function CardShape(): Shape
{
    static $shape;

    return $shape ??= Compile::shape(
        div(h2(Slot::value('title')))->class('card')
    );
}
```

`pure compile --list` reports the marked builder as `(template)`, alongside
registered `(component)` units and standalone `(shape)` files. `pure check`
validates a declared return type of `Pure\Compile\Shape` or a tag. The marker is
tooling metadata; it does not register a second component and it does not run a
function during rendering.

## Registry boundary

`Pure\Component\Registry` is deliberately marked `@internal`. It stores unit
metadata, artifacts, and binders, and the compiler and `Call` implementation
use it internally. The following methods may appear in older guides or internal
code, but their signatures are not the compatibility promise for applications:

- `Registry::register()` and `Registry::prepare()`;
- `Registry::slots()`, `Registry::names()`, `Registry::unitsFor()`, and reset
  helpers;
- the binder returned by `Registry::component()`.

Use `register()` and `component()` in application code. If a lower-level binder
is unavoidable, isolate it behind your own adapter so a future internal change
does not leak into your component contracts.

## PHPStan integration

The project ships public static-analysis classes and enables them in
`phpstan.neon`:

- `Pure\StaticAnalysis\ComponentCallCollector` and
  `Pure\StaticAnalysis\RegistryCallCollector` collect registrations and literal
  calls;
- `Pure\StaticAnalysis\UnknownComponentRule` reports a literal call such as
  `component('Crad')` when the analysed project registers `Card`;
- the rule has the `@api` marker and reports the identifier
  `purephp.unknownComponent`.

Run the complete project analysis, because collected registrations are only
available after all files are analysed:

```bash
composer phpstan
```

The rule is skipped for single-file analysis. Paths and
`component(__FUNCTION__)` are resolved by their file and function context rather
than treated as unknown literals. Keep dynamic names in application code when a
component is selected from data, and validate the data at the boundary.

## Related pages

- [Components](/guide/components) — composition, props, children, and pages
- [Compile API](/api/compile) — Shape, Renderer, Slot, cache, and artifact APIs
- [Troubleshooting](/guide/troubleshooting) — registry, prop, and artifact symptoms
- [Upgrading & Releases](/guide/upgrading) — contract changes and rebuild steps
