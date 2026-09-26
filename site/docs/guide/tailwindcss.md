# PurePHP with Tailwind CSS

**Prerequisites**: [Components](/guide/components); **On this page**: a pinned Tailwind CSS v4 build and accessible component examples.

PurePHP supplies the HTML structure and Tailwind CSS supplies utility classes. This
page uses one explicit major version: **Tailwind CSS 4.3.3** with the dedicated
`@tailwindcss/cli` package. It uses the v4 CSS-first flow; it does not mix v3
configuration or `@tailwind` directives into the same project.

## Quick Start

### 1. Install the pinned toolchain

Install PurePHP and the exact Tailwind packages:

```bash
composer require yonld/purephp
npm install --save-dev --save-exact tailwindcss@4.3.3 @tailwindcss/cli@4.3.3
```

Commit `package-lock.json` and use `npm ci` in repeatable builds. If you need a
newer Tailwind release, change both packages deliberately and rerun the CSS build;
do not let one package silently move to another major.

### 2. Create the CSS entry

Create `src/input.css`. The regular CSS import is the v4 entry point. The
`@source` paths are relative to this file and point at the PHP source directories
that contain class strings:

```css [src/input.css]
@import "tailwindcss";
@source "../components";
@source "../views";
```

Add another `@source` line for every application directory that contains PHP
templates. There is no required `tailwind.config.js` in this flow, and the v3
`@tailwind base`, `@tailwind components`, and `@tailwind utilities` directives are
not used.

### 3. Build the stylesheet

Run these commands from the project root with the dedicated CLI and the same
input and output paths shown above:

```bash
npx @tailwindcss/cli -i ./src/input.css -o ./public/app.css --watch
```

For a production build, use the pinned lockfile and minify the same file:

```bash
npm ci
npx @tailwindcss/cli -i ./src/input.css -o ./public/app.css --minify
```

The equivalent `package.json` scripts are:

```json
{
  "scripts": {
    "css:watch": "npx @tailwindcss/cli -i ./src/input.css -o ./public/app.css --watch",
    "css:build": "npx @tailwindcss/cli -i ./src/input.css -o ./public/app.css --minify"
  }
}
```

Load the generated file from the document, not the input file:

```html
<link rel="stylesheet" href="/app.css">
```

## Basic Usage

The PHP examples below are root-level files. If you move one into a nested
component directory, adjust its `vendor/autoload.php` path accordingly.

### Simple component

Static class strings are part of the data-free tree; the title and content are
request values in slots:

```php [card.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, p};

function Card(string $title, string $content, string $variant = 'default'): string
{
    static $renders = [];

    $baseClasses = 'rounded-lg shadow-md p-6 bg-white';
    $variantClasses = match ($variant) {
        'primary' => 'border-l-4 border-blue-500',
        'success' => 'border-l-4 border-green-500',
        'warning' => 'border-l-4 border-yellow-500',
        'danger' => 'border-l-4 border-red-500',
        default => 'border border-gray-200',
    };

    $render = $renders[$variant] ??= Compile::shape(
        div(
            h1(Slot::value('title'))->class('text-xl font-bold text-gray-900 mb-2'),
            p(Slot::value('content'))->class('text-gray-600 leading-relaxed')
        )->class("{$baseClasses} {$variantClasses}")
    );

    return $render([
        'title' => $title,
        'content' => $content,
    ]);
}

echo Card('Welcome to PurePHP', 'Styled with Tailwind CSS', 'primary');
```

### Responsive layout

The grid is a shape with a raw slot for already-rendered project cards. The image
URL is a value slot, so it is escaped as an attribute:

```php [responsive-grid.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h2, img, p};

function ProjectCard(string $title, string $description, string $image): string
{
    static $render = null;

    $render ??= Compile::shape(
        div(
            img()->src(Slot::value('image'))->alt(Slot::value('title'))
                ->class('w-full h-48 object-cover rounded-t-lg'),
            div(
                h2(Slot::value('title'))->class('text-lg font-semibold mb-2'),
                p(Slot::value('description'))->class('text-gray-600 text-sm')
            )->class('p-4')
        )->class('bg-white rounded-lg shadow-md overflow-hidden')
    );

    return $render([
        'title' => $title,
        'description' => $description,
        'image' => $image,
    ]);
}

function ResponsiveGrid(array $items): string
{
    static $render = null;

    $render ??= Compile::shape(
        div(Slot::raw('items'))
            ->class('grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6')
    );

    $cards = [];

    foreach ($items as $item) {
        $cards[] = ProjectCard(
            $item['title'],
            $item['description'],
            $item['image']
        );
    }

    return $render(['items' => implode('', $cards)]);
}

echo ResponsiveGrid([
    ['title' => 'Project 1', 'description' => 'Description 1', 'image' => 'image1.jpg'],
    ['title' => 'Project 2', 'description' => 'Description 2', 'image' => 'image2.jpg'],
    ['title' => 'Project 3', 'description' => 'Description 3', 'image' => 'image3.jpg'],
]);
```

### Accessible form fields

`FormField()` dispatches on the requested type. A `textarea` value is a child of
`textarea()`, not a `type="textarea"` input. The label is connected with `for`,
the control exposes `aria-invalid`, and `aria-describedby` points to the error
only when an error exists.

```php [contact-form.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{button, div, form, input, label, span, textarea};

const INPUT_CLASS = 'w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 border-gray-300';

function FormField(
    string $labelText,
    string $name,
    string $type = 'text',
    string $placeholder = '',
    string $value = '',
    bool $required = false,
    string $inputClass = INPUT_CLASS,
    string $error = ''
): string {
    static $renders = [];

    $key = "{$labelText}|{$name}|{$type}|{$placeholder}|" . (int)$required;
    $control = $type === 'textarea'
        ? textarea(Slot::value('value'))
            ->name($name)
            ->id($name)
            ->placeholder($placeholder)
            ->required($required)
            ->class(Slot::value('inputClass'))
            ->aria_invalid(Slot::value('ariaInvalid'))
            ->aria_describedby(Slot::value('describedBy')->default(null))
        : input()
            ->type($type)
            ->name($name)
            ->id($name)
            ->placeholder($placeholder)
            ->required($required)
            ->value(Slot::value('value'))
            ->class(Slot::value('inputClass'))
            ->aria_invalid(Slot::value('ariaInvalid'))
            ->aria_describedby(Slot::value('describedBy')->default(null));

    $render = $renders[$key] ??= Compile::shape(
        div(
            label($labelText)
                ->for($name)
                ->class('block text-sm font-medium text-gray-700 mb-1'),
            $control,
            Slot::if(
                'error',
                span(Slot::value('error'))
                    ->id("{$name}-error")
                    ->class('text-red-500 text-sm mt-1')
                    ->role('alert')
            )
        )->class('mb-4')
    );

    return $render([
        'value' => $value,
        'inputClass' => $inputClass,
        'ariaInvalid' => $error !== '' ? 'true' : 'false',
        'describedBy' => $error !== '' ? "{$name}-error" : null,
        'error' => $error,
    ]);
}

function ContactForm(array $fields): string
{
    static $render = null;

    $render ??= Compile::shape(
        form(
            Slot::raw('fields'),
            button('Submit')
                ->type('submit')
                ->class('w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200')
        )->class('max-w-md mx-auto bg-white p-6 rounded-lg shadow-md')
    );

    $html = '';

    foreach ($fields as $field) {
        $html .= FormField(
            labelText: $field['label'],
            name: $field['name'],
            type: $field['type'] ?? 'text',
            placeholder: $field['placeholder'] ?? '',
            value: $field['value'] ?? '',
            required: $field['required'] ?? false,
            inputClass: $field['inputClass'] ?? INPUT_CLASS,
            error: $field['error'] ?? ''
        );
    }

    return $render(['fields' => $html]);
}

echo ContactForm([
    [
        'label' => 'Name',
        'name' => 'name',
        'placeholder' => 'Enter your name',
        'required' => true,
    ],
    [
        'label' => 'Email',
        'name' => 'email',
        'type' => 'email',
        'placeholder' => 'Enter your email',
        'value' => 'not-an-email',
        'required' => true,
        'error' => 'Enter a valid email address',
    ],
    [
        'label' => 'Message',
        'name' => 'message',
        'type' => 'textarea',
        'placeholder' => 'Enter your message',
        'value' => 'Tell us what you need.',
    ],
]);
```

The example uses `Slot::value('value')` for both input attributes and textarea
children, so submitted values are escaped. Keep class names in application code
or configuration, as in the example: `Slot::value()` escapes but does not
validate class tokens, so never pass raw user input as a Tailwind class.

## Advanced Usage

### Dynamic class names

Choose a variant, size, and state from a fixed map. Only the label is dynamic:

```php [action-button.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\button;

function ActionButton(
    string $text,
    string $variant = 'primary',
    string $size = 'md',
    bool $disabled = false,
    bool $fullWidth = false
): string {
    static $renders = [];

    $baseClasses = 'font-medium rounded-md transition duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2';
    $variantClasses = match ($variant) {
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
        'secondary' => 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500',
        'success' => 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'outline' => 'border border-gray-300 text-gray-700 hover:bg-gray-50 focus:ring-blue-500',
        default => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
    };
    $sizeClasses = match ($size) {
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-base',
        'lg' => 'px-6 py-3 text-lg',
        default => 'px-4 py-2 text-base',
    };
    $widthClasses = $fullWidth ? 'w-full' : '';
    $disabledClasses = $disabled ? 'opacity-50 cursor-not-allowed' : '';
    $allClasses = trim("{$baseClasses} {$variantClasses} {$sizeClasses} {$widthClasses} {$disabledClasses}");
    $key = "{$variant}|{$size}|" . (int)$disabled . (int)$fullWidth;

    $render = $renders[$key] ??= Compile::shape(
        button(Slot::value('text'))
            ->class($allClasses)
            ->disabled($disabled)
    );

    return $render(['text' => $text]);
}

echo ActionButton('Primary Button', 'primary', 'lg');
```

### Theme toggle

The button exposes the next theme as data. The application's small JavaScript
listener can toggle the document class; the PHP side only renders the trusted
markup and static class list.

```php [theme.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{button, div, h1, main};

function Page(string $title): string
{
    static $render = null;

    $render ??= Compile::shape(
        main(h1(Slot::value('title')))->class('container mx-auto p-6')
    );

    return $render(['title' => $title]);
}

function ThemeToggle(string $currentTheme = 'light'): string
{
    static $renders = [];

    $nextTheme = $currentTheme === 'light' ? 'dark' : 'light';
    $render = $renders[$currentTheme] ??= Compile::shape(
        button('Toggle theme')
            ->type('button')
            ->data_theme($nextTheme)
            ->class('fixed top-4 right-4 px-4 py-2 rounded-md bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200')
    );

    return $render([]);
}

function ThemeProvider(string $theme, string $toggle, string $page): string
{
    static $renders = [];

    $themeClasses = match ($theme) {
        'dark' => 'bg-gray-900 text-white',
        'light' => 'bg-white text-gray-900',
        default => 'bg-white text-gray-900',
    };
    $render = $renders[$theme] ??= Compile::shape(
        div(
            Slot::raw('toggle'),
            Slot::raw('page')
        )->class("min-h-screen {$themeClasses}")
    );

    return $render(['toggle' => $toggle, 'page' => $page]);
}

echo ThemeProvider('dark', ThemeToggle('dark'), Page('Dashboard'));
```

### Merge class names

Use the built-in `clx()` helper for a small, explicit class list:

```php [class-list.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use function Pure\Utils\clx;

$classes = clx(
    'base-class',
    'another-class',
    [
        'active' => true,
        'error' => false,
        'text-red-500' => false,
    ]
);

echo $classes;
```

## Next steps

1. [Basic Usage](/guide/basic-usage) — inspect the generated tag tree and fragments.
2. [Components](/guide/components) — move static shapes into registered units.
3. [Compiled Rendering](/guide/compiled) — understand memoization and slot binding.
4. [HTMX](/guide/htmx) — return the styled fragment from a server endpoint.
5. [Artifacts & Deployment](/guide/artifacts) — run the CSS build and PHP checks in CI.

Tailwind v4.3.3 is the version used by this page. Keep the package versions,
`@source` paths, and CLI commands together when upgrading the toolchain. See the
[Tailwind CSS documentation](https://tailwindcss.com/docs) for the full utility
reference.
