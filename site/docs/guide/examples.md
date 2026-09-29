---
title: Examples
description: Run the PurePHP bootstrap, event-counter, and XML examples, including strict, plain, and cover routes.
---

# Examples

The repository keeps runnable examples under `examples/`. They use the same
component, compiled, and plain-view paths so you can compare a small production
shape with a dependency-free view.

## Start with a small snippet

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{a, div};

div(
    'Hello ',
    a('PHP')->href('https://www.php.net')
)->class('container')->print();
```

A component adds a typed prop contract and a data-free shape:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

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

echo Card()->title('Card Title')->content('Card Content');
```

## Children, lists, and buttons

This unit shows all three common data paths in one shape: children
through the reserved raw slot, repeated records through `Slot::each()`, and
button attributes through value slots.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

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

Children are passed to the call, lists can be bound with `Slot::each()`, and
component calls implement `Pure\Core\Markup`, so they nest in a tag tree. For the
complete vocabulary, start with [Quick Start](/guide/getting-started) and then
[Components](/guide/components).

## Prepare the repository

The examples are not shipped in the Composer package, so clone the repository
and run the commands below from its root. A clone has no `vendor/bin/pure` —
Composer creates that proxy for installed packages only — so use `bin/pure`:

```bash
git clone https://github.com/YonLD/purephp.git
cd purephp
composer install
php bin/pure compile --plain examples/bootstrap
php bin/pure compile --plain examples/event-counter
php bin/pure compile --plain examples/xml
```

Use `--list` to inspect discovered units. The output labels are `(component)`,
`(shape)`, and `(template)`; a page is not a separate file type:

```bash
php bin/pure compile --list examples
```

## Bootstrap MVC example

`examples/bootstrap` is a small MVC-style application. Controllers stay thin,
DAOs read data, services turn records into bindings, and component units own
their markup. The `features` and `pricing` pages have both a strict artifact and
a plain view. The cover page is static markup and intentionally has neither
variant.

| Route | What it renders |
| --- | --- |
| `/cover` | Static `views/cover.php`; no compile step. |
| `/pure/features` | Page function plus `*.pure.php` component artifacts. |
| `/plain/features` | `features.plain.php` after component bindings are rendered. |
| `/pure/pricing` | Page function plus `*.pure.php` component artifacts. |
| `/plain/pricing` | `pricing.plain.php` after component bindings are rendered. |

Run its router from the repository root:

```bash
php -S localhost:8000 -t examples/bootstrap/public \
    examples/bootstrap/public/index.php
```

Visit `http://localhost:8000/cover`, then compare `/pure/features` with
`/plain/features` and `/pure/pricing` with `/plain/pricing`. An unknown path
returns a 404 page that lists all five routes.

The plain loader extracts data into locals and requires the generated view. A
`Call` binding is converted to markup before that view loads, which is why the
plain file itself does not need PurePHP at render time.

## Event counter example

`examples/event-counter` demonstrates a small page with a random initial value
(`random_int(0, 100)`) and browser-side `+` / `-` controls. It has the same
strict and plain variants:

| Route | What it renders |
| --- | --- |
| `/` or `/index.php` | Redirects to `/plain`. |
| `/pure` | The page function and `counter.pure.php`. |
| `/plain` | `counter.plain.php`, with no library call in the view. |

Run it with:

```bash
php -S localhost:8000 -t examples/event-counter/public \
    examples/event-counter/public/index.php
```

Open `http://localhost:8000/pure` or `/plain`, then use the counter buttons. The
JavaScript and CSS are static files in `public/`; the router hands them back to
the built-in server, which delivers them directly.

## XML example

`examples/xml` shows XML roots, nested item shapes, optional fields, and a
`#[Template]` builder. It exercises the `XML::state` and `XML::address` branches;
its response is XML rather than HTML:

| Route or command | What it does |
| --- | --- |
| `/` or `/index.php` | Redirects to `/plain`. |
| `/pure` | The page function and `xml.pure.php`. |
| `/plain` | `xml.plain.php`, rendered as a document. |
| `php examples/xml/write.php` | Writes `examples/xml/example.xml` and prints its byte count. |

Run the web version with:

```bash
php -S localhost:8000 -t examples/xml/public examples/xml/public/index.php
```

Or write the file from the repository root:

```bash
php examples/xml/write.php
```

The XML declaration is supplied by `renderXML()`; the template itself describes
the tree. `AddressShape()` demonstrates `Slot::each()` for a list and
`Slot::if()` for an optional city.

## Compare strict and plain output

For ordinary data, a generated plain view is byte-identical to the strict
artifact, with the document header included when the root is an HTML or XML
document. The plain path is useful when the deployment ships only `public/` and
`views/` and does not install PurePHP. It is an include-based view, not a
second template language.

For troubleshooting a missing route, stale artifact, or escaped fragment, see
[Troubleshooting](/guide/troubleshooting). For release-specific rebuild steps,
see [Upgrading](/guide/upgrading).
