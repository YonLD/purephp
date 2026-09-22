# PurePHP with HTMX

**Prerequisites**: [Components](/guide/components); **On this page**: dynamic interaction with HTMX, no client-side framework.

PurePHP and HTMX form a powerful combination that allows you to build dynamic, responsive user interfaces while keeping your PHP backend clean and simple.

::: tip HTMX fragments fit compiled shapes
HTMX fragments are a natural fit for compiled shapes: `hx-*` attributes are ordinary static tag attributes, so they are set once on the shape, and the endpoint renders the same shape with request data. See [Compiled Rendering](/guide/compiled).
:::

## Why This Combination?

- **PurePHP**: Provides componentized PHP template rendering
- **HTMX**: Offers dynamic interaction capabilities without JavaScript
- **Perfect Match**: PurePHP handles server-side rendering, HTMX handles client-side interactions

## Quick Start

### 1. Install Dependencies

```bash
composer require yonld/purephp
```

Then include HTMX in your HTML:

```html
<script src="https://unpkg.com/htmx.org@2.0.4"></script>
```

### 2. Create Dynamic Components

The counter text is a bound slot; the endpoint renders the same `CounterValue`
unit with the new count. Each component is its own unit:

```php [components/CounterValue.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\p;

function CounterValue(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(CounterValue(...),
    factory: static fn () => p('Current count: ', Slot::value('count'))->id('counter'),
    prepare: static function (int $count): array {
        return ['count' => $count];
    }
);
```

```php [components/Counter.cmp.php]
<?php

require_once __DIR__ . '/CounterValue.cmp.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button, div};

function Counter(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Counter(...),
    factory: static fn () =>
        div(
            Slot::raw('counter'),
            button('Increment')
                ->hx_post('/increment')
                ->hx_target('#counter')
                ->hx_swap('innerHTML')
        )->class('counter'),
    prepare: static function (int $count): array {
        return ['counter' => CounterValue()->count($count)];
    }
);
```

```php [index.php]
<?php

require __DIR__ . '/components/Counter.cmp.php';

// Render the page
echo Counter()->count(0);

// Handle HTMX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/increment') {
    $count = (int)($_COOKIE['count'] ?? 0) + 1;
    setcookie('count', $count);

    echo CounterValue()->count($count);
    exit;
}
```

### 3. Infinite Scroll List

```php
<?php

use function Pure\HTML\{button, div, li, ul};

function TodoList()
{
    return div(
        ul()->id('todos'),
        button('Load More')
            ->hx_get('/todos?page=1')
            ->hx_target('#todos')
            ->hx_swap('beforeend')
    )->class('todo-list');
}

// Handle HTMX request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], '/todos') === 0) {
    $page = (int)($_GET['page'] ?? 1);
    $todos = getTodos($page); // Get todo items

    foreach ($todos as $todo) {
        echo li($todo['title'])->class('todo-item');
    }
    exit;
}
```

### 4. Live Search

The result list is a component; every result title is bound with `Slot::value()`,
and the endpoint renders the list component with the search results:

```php [components/SearchResult.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\div;

function SearchResult(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(SearchResult(...),
    factory: static fn () => div(Slot::value('title'))->class('search-result'),
    prepare: static function (string $title): array {
        return ['title' => $title];
    }
);
```

```php [components/ResultList.cmp.php]
<?php

require_once __DIR__ . '/SearchResult.cmp.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\div;

function ResultList(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(ResultList(...),
    factory: static fn () => div(Slot::raw('results')),
    prepare: static function (array $results): array {
        $items = [];

        foreach ($results as $result) {
            $items[] = SearchResult()->title($result['title']);
        }

        return ['results' => $items];
    }
);
```

```php [components/SearchBox.cmp.php]
<?php

require_once __DIR__ . '/ResultList.cmp.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, input};

function SearchBox(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(SearchBox(...),
    factory: static fn () =>
        div(
            input()
                ->type('text')
                ->placeholder('Search...')
                ->hx_get('/search')
                ->hx_trigger('keyup changed delay:500ms')
                ->hx_target('#results'),
            div(Slot::raw('list'))->id('results')
        )->class('search-box'),
    prepare: static function (iterable|string|Stringable $list): array {
        return ['list' => $list];
    }
);
```

```php [index.php]
<?php

require __DIR__ . '/components/SearchBox.cmp.php';

// Render the page with an empty result list
echo SearchBox()->list(ResultList()->results([]));

// Handle HTMX request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['REQUEST_URI'] === '/search') {
    $query = $_GET['q'] ?? '';
    $results = searchItems($query); // Search items

    echo ResultList()->results($results);
    exit;
}
```

## Next Steps

- [HTMX Documentation](https://htmx.org/docs/)
- [PurePHP Components Guide](/guide/components)
- [PurePHP Events Guide](/guide/events)
