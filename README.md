# Purephp

[![Tests](https://github.com/YonLD/purephp/workflows/Tests/badge.svg)](https://github.com/YonLD/purephp/actions)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Purephp is a PHP templating engine inspired by ReactJS functional components.

## 📖 Documentation

- **English**: [https://yonld.github.io/purephp/](https://yonld.github.io/purephp/)
- **中文**: [https://yonld.github.io/purephp/zh/](https://yonld.github.io/purephp/zh/)

Start with the [Quick Start](https://yonld.github.io/purephp/guide/getting-started),
then shapes and slots, and reach components last — a component is a wrapper
around shapes.

## Why use Purephp?

To enjoy pure PHP programming.

In traditional approaches, mixing HTML code, PHP code, and other template syntax in the view layer can be frustrating for developers.

However, with Purephp:
+ Everything is 100% native PHP code.
+ Encapsulate components to eliminate repetitive HTML code.
+ The syntax closely resembles HTML.
+ Compile data-free **shapes** into flat renderers, for performance on par with compiled template engines.

## Install

```bash
composer require yonld/purephp
```

## Quick start

### 1. Hello, PurePHP

Build a tag tree and print it — no template syntax, just PHP:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{div, h1, p};

div(
    h1('Hello PurePHP'),
    p('This renders immediately.')
)->class('container')->print();
```

### 2. A component

A component is one file: a call function that returns a `Pure\Component\Call`,
plus the template it renders and the typed props of its `prepare()` hook,
registered lazily so `pure compile` can precompile it:

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

echo Card()->title('Card Title')->content('Card Content');
```

Output:

```html
<div class="card"><h2>Card Title</h2><p>Card Content</p></div>
```

How it fits together:

- The template is a data-free **shape**: `Slot::value('title')` is a placeholder
  filled from the data array at render time.
- `register(Card(...))` derives the name and file from the call function and
  only stores the factory — it builds nothing.
- `prepare()` is the typed prop contract: its parameters are the props, PHP
  enforces their types, and the returned array binds the template.
- Props are set as fluent setters; children are passed to the call itself, and
  the result nests wherever a tag does.

### 3. Children, lists and buttons

The same unit, called fluently:

```php [components/Card.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button, div, h2, li, ul};

function Card(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Card(...),
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
    Card(h2('Pro'))
        ->type('Free')
        ->features([['value' => '10 users'], ['value' => '2 GB']])
        ->text('Sign up for free')
        ->class('btn btn-lg btn-block btn-outline-primary')
);
```

### 4. Production

- **Precompile**: `vendor/bin/pure compile components` builds a `*.pure.php`
  artifact per unit; a request that finds a fresh artifact never builds the
  template. `pure compile --list` prints the units found.
- **Cache**: under standard PHP-FPM every request starts fresh, so enable
  `Compile::cachePath()` (or rely on precompiled artifacts) to load generated
  renderers instead of rebuilding them; long-running workers keep them in memory.
- **Guard**: while developing, `Compile::guard(true)` (or `PURE_COMPILE_GUARD=1`)
  reports shapes rebuilt per request, bindings the template never reads (with a
  `did you mean`), and attribute names one edit away from a standard one.
- **Contract check**: `pure check` validates props against the template's slots;
  `#[Prop]` states the slot, item shape, requiredness and deprecation of a prop,
  `#[Trusted]` marks a prop that carries markup, and `#[Binds]` declares the
  keys of a hook whose returned array cannot be read.

A call renders a fragment; a full document's header is the caller's to prepend
(`$root->documentHeader()`, or a literal `<!DOCTYPE html>` /
`<?xml version="1.0"?>`, or `renderHTML()` / `renderXML()`).

See the [compiled rendering guide](https://yonld.github.io/purephp/guide/compiled)
for caching, conditionals and mixed lists, and
[Artifacts & Deployment](https://yonld.github.io/purephp/guide/artifacts) for
the artifact contract and freshness rules.

## Snippets and debugging

For small fragments, one-off snippets and debugging you can build a regular tag
tree and render it immediately:

```php
<?php

use function Pure\HTML\a;
use function Pure\HTML\div;

div(
    'Hello ',
    a('PHP')->href('https://www.php.net')
)->class('container')->style('background: #fff;')->data_key('primary')->print();
```

Output:

```html
<div class="container" style="background: #fff;" data-key="primary">
  Hello <a href="https://www.php.net">PHP</a>
</div>
```

The tag tree's `render()` and `print()` are the debug/snippet outlet. Production
pages should compile shapes, because a shape is compiled and static markup is
escaped once instead of on every render.

## Compiled components

Inside a template, nested shapes use `Slot::child()`, lists use `Slot::each()`,
and conditionals use `Slot::if()`. Everything else is plain PHP.

A parent takes its children's markup as an ordinary value and passes it through
a raw slot: `div(Slot::raw('body'))` bound as `component('Page')->body(Card(...))`.
Pre-rendered markup passed into a raw slot needs no `(string)` cast, and an
array of them is concatenated in order.

For production, `pure compile` precompiles every `*.cmp.php` unit (and every
lower-level `*.shape.php` template) into a `*.pure.php` artifact that returns a
`Renderer` without building the shape tree:

```bash
vendor/bin/pure compile components            # *.pure.php: the compiled renderer
vendor/bin/pure compile --plain components    # + *.plain.php: a dependency-free view
vendor/bin/pure compile --list components     # name -> file (component|page)
vendor/bin/pure check components              # slots vs. bindings vs. parameters
```

```php
<?php

use Pure\Core\HTML;

$page = require __DIR__ . '/page.pure.php';

echo $page->render(['title' => 'Card Title']);      // the view body
echo HTML::DOCUMENT_HEADER . $page->render($data);  // a whole document
```

A `*.plain.php` view is markup and native PHP only — load it by extracting the
data into locals and nothing of purephp is needed at render time:

```php
<?php

ob_start();
extract($data, EXTR_SKIP);
require __DIR__ . '/views/index.plain.php';
$html = (string)ob_get_clean();
```

`pure compile --check` reports stale or missing artifacts for CI
(`--check --plain` covers both flavors). See
[Artifacts & Deployment](https://yonld.github.io/purephp/guide/artifacts#precompiled-artifacts)
for the artifact contract, the freshness rules and the plain-view caveats.

## Examples

`examples/bootstrap` is a small MVC setup with three pages behind one router:

- controllers stay thin: `app/dao/` reads the records, `app/services/` turns
  them into the props of one component, and each component fetches its own
  slice there — the page function carries no page data;
- `views/features.cmp.php` and `views/pricing.cmp.php` are component units that
  compile into a strict artifact (`*.pure.php`, loaded by the unit's binder)
  and a dependency-free view (`*.plain.php`, required by the example's `plain()`
  helper); the two controllers of a page share the bindings its fluent component
  calls produce (`featuresBindings()` / `pricingBindings()`), which the plain
  loader renders to strings before the view loads;
- the cover page is static markup through the string renderer
  (`views/cover.php`), so it has neither variant.

Routes:

```
/cover             the static cover page
/plain/features    the plain features view
/plain/pricing     the plain pricing view
/pure/features     the compiled features artifact
/pure/pricing      the compiled pricing artifact
```

```bash
vendor/bin/pure compile --plain examples/bootstrap
php -S localhost:8000 -t examples/bootstrap/public \
    examples/bootstrap/public/index.php
# http://localhost:8000/cover, and either flavor of each page:
# /pure/features /plain/features /pure/pricing /plain/pricing
```

A request that matches nothing gets a 404 that lists every route.

`event-counter` and `xml` follow the same layout — a `views/<page>.cmp.php`
unit plus a `public/index.php` router for `/`, `/pure` and `/plain` — and `xml`
adds `write.php`, the CLI entry that writes `example.xml`.

Every artifact is byte-identical to its template, and every plain view to its
artifact, preceded by the document header only when the view's root heads a
document (`<html>` or an XML tree; a fragment view starts with its markup). See
[the examples](examples).

## Development

```bash
composer quality    # syntax-check + cs-check + phpstan + tests: run this before committing
composer test       # PHPUnit only
composer cs-fix     # apply the PHP CS Fixer rules
composer phpstan    # static analysis (level 9)
composer bench      # benchmarks
```

The documentation site lives in `site/` (VitePress):
`npm run docs:dev` to preview locally, `npm run docs:build` to build it
(this also regenerates `llms.txt` and verifies its links).

## License

MIT © YonLD
