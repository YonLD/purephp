# Quick Start

**Prerequisites**: PHP 8.1+, Composer; **On this page**: install PurePHP, run a component directly, and wire the same component into a real front controller.

This guide takes the smallest useful path through PurePHP: install the package,
render a tag tree once, then define a typed component and use it from an application
entry point. A component is a callable unit with a data-free template; `Slot`
placeholders receive the values supplied by its typed `prepare()` hook.

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

### Install the package

Run this in the root of your project:

```bash
composer require yonld/purephp
```

### Verify the installation

Create `test.php` in that project root:

```php [test.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{div, h1, p};

div(
    h1('PurePHP Installation Successful'),
    p('The package and Composer autoloader are working.')
)->print();
```

Run it:

```bash
php test.php
```

The output is an HTML fragment. This immediate-rendering API is useful for a
first check; the application below uses a component and a front controller.

## Create the first application

### 1. Create the project

```bash
mkdir my-purephp-app
cd my-purephp-app
composer require yonld/purephp
mkdir -p components public
```

Keep `composer.json`, `vendor/`, `components/`, and `public/` in this layout. The
paths in the examples below assume that layout.

### 2. Define a component

Create `components/Card.cmp.php`:

```php [components/Card.cmp.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(): Call
{
    return component(__FUNCTION__);
}

register(
    Card(...),
    factory: static fn () => div(
        h2(Slot::value('title')),
        p(Slot::value('content'))
    )->class('card'),
    prepare: static fn (string $title, string $content): array => [
        'title' => $title,
        'content' => $content,
    ],
);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    echo Card()->title('Title')->content('Content');
}
```

`Slot::value()` marks a text position. The `prepare()` parameters are the typed
props accepted by the fluent call, and the returned array binds the template.
The file is safe to `require` from an application: the small CLI guard runs only
when this file itself is executed, so a direct smoke test and a front controller
can share the same unit.

### 3. Run the component directly

Run the unit as a standalone script:

```bash
php components/Card.cmp.php
```

Expected output:

```html
<div class="card"><h2>Title</h2><p>Content</p></div>
```

This is a component smoke test, not a complete HTML document. It proves that the
autoloader, registration, prop contract, and renderer work together.

### 4. Add a real application entry

Create `public/index.php`:

```php [public/index.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../components/Card.cmp.php';

use function Pure\HTML\{body, head, html, meta, title};
use function Pure\Utils\renderHTML;

$title = is_string($_GET['title'] ?? null) ? trim($_GET['title']) : 'Title';
$content = is_string($_GET['content'] ?? null) ? trim($_GET['content']) : 'Content';

echo renderHTML(
    html(
        head(
            meta()->charset('utf-8'),
            title('Card')
        ),
        body(Card()->title($title)->content($content))
    )
);
```

The entry point supplies request data, calls the component, and adds the document
shell. Start PHP's development server from the project root:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Open `http://127.0.0.1:8000/?title=Hello&content=From+the+entry+point`.
The component is still the same unit; only the caller and document wrapper are
different.

## Next steps

Follow the path in this order:

1. [Basic Usage](/guide/basic-usage) — learn the tag API and fragment rendering.
2. [Core Concepts](/guide/concepts) — understand tag trees, shapes, slots, and components.
3. [Components](/guide/components) — compose units and define page-level prop contracts.
4. [HTMX](/guide/htmx) — return HTML fragments for server-driven interactions.
5. [Tailwind CSS](/guide/tailwindcss) — style the same components with a pinned CSS build.
6. [Compiled Rendering](/guide/compiled) and [Artifacts & Deployment](/guide/artifacts) — add caching, artifacts, and CI checks.

For production, keep the front controller thin, validate request data at its
boundary, and use `pure check` plus the deployment steps in
[Artifacts & Deployment](/guide/artifacts#contract-check).
