# Quick Start

**Prerequisites**: none; **On this page**: install PurePHP and run your first component.

This guide helps you install PurePHP and create your first application: a
**component** — one file with a call function and a data-free template whose
dynamic values are **Slot** placeholders, typed through a `prepare()` hook.
Everything data-driven in PurePHP is built this way; snippets can also render
tag trees immediately, as shown in the installation check below.

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

### Using Composer

Run the following command in your project directory:

```bash
composer require yonld/purephp
```

### Verify Installation

Create a simple test file `test.php`:

```php [test.php]
<?php

require_once __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{div, h1, p};

div(
    h1('PurePHP Installation Successful'),
    p('Congratulations! PurePHP is correctly installed.')
)->print();
```

Run the test file:

```bash
php test.php
```

If you see HTML output, the installation was successful. Note that this uses
immediate rendering — the right tool for a quick check; the application below
renders as a component.

## Create Your First Application

### 1. Create the Project Directory

```bash
mkdir my-purephp-app
cd my-purephp-app
composer require yonld/purephp
```

### 2. Create Your First Component

Create `components/Card.cmp.php`. The template is a data-free tree: dynamic
values are `Slot` placeholders, bound by the typed props of the `prepare()`
hook:

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
    factory: static fn () => div(
        h2(Slot::value('title')),
        p(Slot::value('content'))
    )->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);

echo Card()->title('Title')->content('Content');
```

- `Slot::value('title')` is a placeholder: at render time the value comes from
  the prop of the same name and is escaped into that position;
- `register(Card(...))` derives the name and the file from the call function
  and stores the factory lazily — it builds nothing;
- `prepare()` is the typed prop contract: PHP enforces the parameter types,
  and the array it returns binds the template.

### 3. Run the Application

```bash
php components/Card.cmp.php
```

Output:

```html
<div class="card"><h2>Title</h2><p>Content</p></div>
```

In a real application the unit sits in its own file, the controller `require`s
it and calls the component with request data — see
[Components](/guide/components).

### 4. Next: Toward Production

The snippet above rebuilds the template on every run — fine for learning.
Production needs three more things:

- **Disk cache and precompiled artifacts** — `Compile::cachePath()` and
  `pure compile`, see [Artifacts & Deployment](/guide/artifacts);
- **The development guard** — reports per-request rebuilds, misspelled
  bindings and similar problems, see [Caching](/guide/compiled#caching);
- **Static contract checking** — `pure check` validates props against slots
  in CI, see [Contract Check](/guide/artifacts#contract-check).

## Next Steps

In order:

- [Basic Usage](/guide/basic-usage) - The tag API for snippets, prototypes and debugging
- [Core Concepts](/guide/concepts) - Tag trees, shapes, slots and components
- [Props and Slots](/guide/props) - Slot types and the data-binding reference
- [Components](/guide/components) - Composition, prop contracts and pages
- [Compiled Rendering](/guide/compiled) - How a component's template compiles
- [Artifacts & Deployment](/guide/artifacts) - `pure compile` artifacts and production deployment
