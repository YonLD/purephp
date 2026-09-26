---
title: What is PurePHP?
description: A progressive introduction to PurePHP, from immediate tag trees to compiled components and production artifacts.
---

# What is PurePHP?

**Start here** if you are new to PurePHP. No prior template language is required;
you only need PHP 8.1 or newer and Composer. The pages are ordered so each step
builds on the vocabulary introduced by the previous one.

PurePHP is a PHP template engine inspired by ReactJS functional components. You
describe UI as native PHP objects that look like HTML, and PurePHP renders an
HTML string. There is no separate template syntax to learn.

## Choose a learning path

| Step | Read | You will learn |
| --- | --- | --- |
| 1 | [Quick Start](/guide/getting-started) | Install the package and render a first tag tree. |
| 2 | [Basic Usage](/guide/basic-usage) | Compose HTML-like elements, attributes, children, and output. |
| 3 | [Core Concepts](/guide/concepts) | Name tags, shapes, slots, and renderers. |
| 4 | [Props and Slots](/guide/props) | Bind scalar text, raw markup, child scopes, lists, and conditionals. |
| 5 | [Components](/guide/components) | Wrap a shape in a call function with typed props and children. |
| 6 | [Compiled Rendering](/guide/compiled) | Compile a shape once and render it with request data. |
| 7 | [Artifacts & Deployment](/guide/artifacts) | Ship `*.pure.php` and `*.plain.php` outputs safely. |

After the core path, choose the integrations and examples that match your app:
[Events](/guide/events), [SVG and XML](/guide/svg-xml), [Utility Functions](/guide/utils),
[HTMX](/guide/htmx), [TailwindCSS](/guide/tailwindcss), and the runnable
[Examples](/guide/examples).

## Two rendering paths

PurePHP keeps two paths deliberately separate:

| Path | What you write | Best for |
| --- | --- | --- |
| **Immediate rendering** | A tag tree containing real values, printed with `render()` or `print()`. | Snippets, prototypes, CLI tools, and debugging. |
| **Compiled rendering** | A data-free shape with `Slot` placeholders, compiled once per worker or loaded from a fresh artifact. | Reusable components and production pages. |

A shape is not a second template language. It is a PHP tag tree whose dynamic
values are replaced by placeholders. See [Core Concepts](/guide/concepts) before
mixing the two paths.

## Hello, PurePHP

The smallest useful program is still ordinary PHP:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{div, h1, p};

div(
    h1('Hello PurePHP'),
    p('This renders immediately.')
)->class('container')->print();
```

For a reusable component, a call function returns a `Pure\Component\Call` and a
lazy factory supplies its shape:

```php
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Card(...), static fn () => div(
    h2(Slot::value('title')),
    p(Slot::value('content'))
)->class('card'));

echo Card()->title('Title')->content('Content');
```

`prepare()` can add the typed prop contract; `pure compile` can then precompile
the unit into a `*.pure.php` artifact. Read [Components](/guide/components) for
children, list slots, pages, and the contract checker.

## Why PurePHP?

- **Native PHP:** the view layer is PHP, not a new template language.
- **HTML-like composition:** helper functions and fluent setters read like tags.
- **Reusable units:** a component is a shape plus a typed call contract.
- **Repeatable rendering:** compiled shapes move static work out of the request
  path while preserving the same escaping and output semantics.

## When something goes wrong

Use the [Troubleshooting](/guide/troubleshooting) page by symptom rather than
searching through implementation details. If you are moving from another
version or a source checkout, read [Upgrading & Releases](/guide/upgrading) first
and rebuild artifacts deliberately.

## Next steps

- [Quick Start](/guide/getting-started) — install, autoload, and run the first page
- [Examples](/guide/examples) — compare `pure`, `plain`, static `/cover`, counter, and XML routes
- [Component API](/api/component) — public component helpers and PHPStan integration
- [Compile API](/api/compile) — canonical Slot, Shape, Renderer, cache, and artifact reference
- [Troubleshooting](/guide/troubleshooting) — fixes for common runtime and build symptoms
