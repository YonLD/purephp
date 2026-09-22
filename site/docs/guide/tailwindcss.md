# PurePHP with TailwindCSS Integration

**Prerequisites**: [Components](/guide/components); **On this page**: combining Tailwind classes with PurePHP components.

The combination of PurePHP and TailwindCSS provides a powerful development experience: component-based PHP templating engine paired with a utility-first CSS framework.

::: tip Static classes, dynamic slots
Reusable components in this guide are compiled shapes: static Tailwind class strings are written once at build time and per-request values arrive through slots. See [Components](/guide/components) and [Compiled Rendering](/guide/compiled). The immediate tag API (`render()` / `print()`) remains available for snippets, and Tailwind finds the class names in both paths because they always live in PHP source.
:::

## Why Choose This Combination?

- **PurePHP**: Provides component-based PHP template rendering
- **TailwindCSS**: Provides utility-first CSS class system
- **Perfect Complement**: PurePHP handles structure and logic, TailwindCSS handles styling

## Quick Start

### 1. Install Dependencies

First install PurePHP:

```bash
composer require yonld/purephp
```

Then install TailwindCSS:

```bash
npm install -D tailwindcss
npx tailwindcss init
```

### 2. Configure TailwindCSS

Configure content paths in `tailwind.config.js`:

```javascript
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.php",
    "./public/**/*.php",
    "./components/**/*.php",
    "./views/**/*.php"
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

### 3. Create CSS File

Create `src/input.css`:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

### 4. Build CSS

```bash
npx tailwindcss -i ./src/input.css -o ./public/output.css --watch
```

## Basic Usage

### Simple Component

The variant decides the static class list, so each variant is memoized as its
own renderer; the title and content are dynamic and become slots:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;
use function Pure\HTML\{div, h1, p};

function Card(string $title, string $content, string $variant = 'default'): string
{
    static $renders = [];

    $baseClasses = 'rounded-lg shadow-md p-6 bg-white';
    $variantClasses = match($variant) {
        'primary' => 'border-l-4 border-blue-500',
        'success' => 'border-l-4 border-green-500',
        'warning' => 'border-l-4 border-yellow-500',
        'danger' => 'border-l-4 border-red-500',
        default => 'border border-gray-200'
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

echo Card('Welcome to PurePHP', 'This is a card component styled with TailwindCSS', 'primary');
```

### Responsive Layout

The grid has no data of its own; it renders each item with `ProjectCard()` and
injects the joined markup through `Slot::raw()`:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;
use function Pure\HTML\{div, h2, p, img};

function ProjectCard(string $title, string $description, string $image): string
{
    static $render;
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

    return $render(['title' => $title, 'description' => $description, 'image' => $image]);
}

function ResponsiveGrid(array $items): string
{
    static $render;
    $render ??= Compile::shape(
        div(Slot::raw('items'))
            ->class('grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6')
    );

    $cards = [];

    foreach ($items as $item) {
        $cards[] = ProjectCard($item['title'], $item['description'], $item['image']);
    }

    return $render(['items' => implode('', $cards)]);
}
echo ResponsiveGrid([
    ['title' => 'Project 1', 'description' => 'Description 1', 'image' => 'image1.jpg'],
    ['title' => 'Project 2', 'description' => 'Description 2', 'image' => 'image2.jpg'],
    ['title' => 'Project 3', 'description' => 'Description 3', 'image' => 'image3.jpg'],
]);
```

### Form Components

Field configuration is passed as arguments; the error message and the
error-state input class are bound per request (`Slot::if()` renders the error
line only when data provides it):

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;
use function Pure\HTML\{form, div, label, input, button, span};

const INPUT_CLASS = 'w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 border-gray-300';

function FormField(
    string $labelText,
    string $name,
    string $type = 'text',
    string $placeholder = '',
    bool $required = false,
    string $inputClass = INPUT_CLASS,
    string $error = ''
): string {
    static $renders = [];

    $key = "{$labelText}|{$name}|{$type}|{$placeholder}|" . (int)$required;

    $render = $renders[$key] ??= Compile::shape(
        div(
            label($labelText)
                ->for($name)
                ->class('block text-sm font-medium text-gray-700 mb-1'),
            input()
                ->type($type)
                ->name($name)
                ->id($name)
                ->placeholder($placeholder)
                ->required($required)
                ->class(Slot::value('inputClass')),
            Slot::if('error', span(Slot::value('error'))->class('text-red-500 text-sm mt-1'))
        )->class('mb-4')
    );

    return $render(['inputClass' => $inputClass, 'error' => $error]);
}

function ContactForm(array $fields): string
{
    static $render;
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
            $field['label'],
            $field['name'],
            $field['type'] ?? 'text',
            $field['placeholder'] ?? '',
            $field['required'] ?? false,
            $field['inputClass'] ?? INPUT_CLASS,
            $field['error'] ?? '',
        );
    }

    return $render(['fields' => $html]);
}

// Only the errored field overrides the default input class
echo ContactForm([
    ['label' => 'Name', 'name' => 'name', 'placeholder' => 'Enter your name', 'required' => true],
    [
        'label' => 'Email',
        'name' => 'email',
        'type' => 'email',
        'placeholder' => 'Enter your email',
        'required' => true,
        'error' => 'Enter a valid email address',
        'inputClass' => 'w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 border-red-500',
    ],
    ['label' => 'Message', 'name' => 'message', 'type' => 'textarea', 'placeholder' => 'Enter your message'],
]);
```

## Advanced Usage

### Dynamic Class Names

Variant, size, and state decide the static class list, so each combination is
memoized as its own renderer. Only the label is dynamic:

```php
<?php

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

    $variantClasses = match($variant) {
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
        'secondary' => 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500',
        'success' => 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'outline' => 'border border-gray-300 text-gray-700 hover:bg-gray-50 focus:ring-blue-500',
        default => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500'
    };

    $sizeClasses = match($size) {
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-base',
        'lg' => 'px-6 py-3 text-lg',
        default => 'px-4 py-2 text-base'
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

### Theme Toggle

The theme decides static class lists, so each theme is memoized as its own
renderer; the toggle and the page are passed in as rendered components and
injected through `Slot::raw()`:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;
use function Pure\HTML\{div, main, h1, button};

function Page(string $title): string
{
    static $render;
    $render ??= Compile::shape(
        main(h1(Slot::value('title')))->class('container mx-auto p-6')
    );

    return $render(['title' => $title]);
}

function ThemeToggle(string $currentTheme = 'light'): string
{
    static $renders = [];

    $newTheme = $currentTheme === 'light' ? 'dark' : 'light';
    $icon = $currentTheme === 'light' ? '🌙' : '☀️';

    $render = $renders[$currentTheme] ??= Compile::shape(
        button("{$icon} Toggle Theme")
            ->onclick("toggleTheme('{$newTheme}')")
            ->class('fixed top-4 right-4 px-4 py-2 rounded-md bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600')
    );

    return $render([]);
}

function ThemeProvider(string $theme, iterable|string $toggle, iterable|string $page): string
{
    static $renders = [];

    $themeClasses = match($theme) {
        'dark' => 'bg-gray-900 text-white',
        'light' => 'bg-white text-gray-900',
        default => 'bg-white text-gray-900'
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

## Merging Class Names

Use the built-in `clx()` helper (see [Utility Functions](/guide/utils)):

```php
<?php

use function Pure\Utils\clx;

$isActive = true;
$hasError = false;

$classes = clx(
    'base-class',
    'another-class',
    [
        'active' => $isActive,
        'error' => $hasError,
        'text-red-500' => $hasError
    ]
);

echo $classes; // Output: base-class another-class active
```

## Next Steps

- [TailwindCSS Documentation](https://tailwindcss.com/docs)
- [PurePHP Component Guide](/guide/components)
- [PurePHP Utility Functions](/guide/utils)
