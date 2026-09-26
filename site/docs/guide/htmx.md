# PurePHP with HTMX

**Prerequisites**: [Components](/guide/components); **On this page**: server-rendered fragments, routing, and safe state changes with HTMX.

PurePHP renders HTML on the server and HTMX swaps the returned HTML into a
page. Keep the boundary small: a normal route returns a document, while an HTMX
route returns only the fragment named by its target.

::: tip Fragments and compiled shapes
`hx-*` attributes are ordinary attributes on a data-free tree. Put them in a
compiled shape, then render the same shape with request data. The examples below
use registered components so the fragment contract stays visible.
:::

## Quick Start

### 1. Install and pin HTMX

Install PurePHP first:

```bash
composer require yonld/purephp
```

This guide pins HTMX **2.0.11**. For a CDN, use the exact file and its published
SRI hash:

```html
<script
  src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.11/dist/htmx.min.js"
  integrity="sha384-2OatzQy1H+Zd/IIrjr1TcuDGqLXeHhbooAyJY1KdQMKnr4LZ22k31GBLdYKHmVjg"
  crossorigin="anonymous"
></script>
```

For a local dependency, record the same version in `package.json` and
`package-lock.json`, then copy the exact file into the web root:

```bash
npm install --save-exact htmx.org@2.0.11
mkdir -p public/assets
cp node_modules/htmx.org/dist/htmx.min.js public/assets/htmx.min.js
```

Serve it with a same-origin URL:

```html
<script src="/assets/htmx.min.js" defer></script>
```

SRI applies to the exact CDN bytes. If you self-host the file, use the pinned
npm lockfile and your normal asset verification instead of copying the CDN hash
to a different build. The PHP front controller below uses the local URL.

### 2. Define the counter fragments

The counter page contains a button and a value. The value is a separate unit so
the POST endpoint can return it without returning the page around it.

```php [components/CounterValue.cmp.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\p;

function CounterValue(): Call
{
    return component(__FUNCTION__);
}

register(
    CounterValue(...),
    factory: static fn () => p('Current count: ', Slot::value('count'))->id('counter'),
    prepare: static fn (int $count): array => ['count' => $count],
);
```

```php [components/Counter.cmp.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/CounterValue.cmp.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button, div};

function Counter(): Call
{
    return component(__FUNCTION__);
}

register(
    Counter(...),
    factory: static fn () => div(
        Slot::raw('counter'),
        button('Increment')
            ->type('button')
            ->hx_post('/increment')
            ->hx_target('#counter')
            ->hx_swap('outerHTML')
            ->hx_headers(Slot::value('csrfHeaders'))
    )->class('counter'),
    prepare: static fn (int $count, string $csrfHeaders): array => [
        'counter' => CounterValue()->count($count),
        'csrfHeaders' => $csrfHeaders,
    ],
);
```

`hx_swap="outerHTML"` replaces the `<p id="counter">` with the new `<p>` returned
by the endpoint. The endpoint never echoes the surrounding page.

### 3. Add real pagination

The list below starts with page 1. Each response appends only `<li>` items and
uses an out-of-band swap to replace the Load More control with the next page's
control. It is real pagination: the next URL is derived from the current page,
and a short final page removes the button.

```php [components/TodoList.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use function Pure\HTML\{button, div, li, span, ul};

const DEMO_TODO_PAGE_SIZE = 2;

function demoTodoRows(): array
{
    return [
        ['title' => 'Read the request'],
        ['title' => 'Render a fragment'],
        ['title' => 'Validate the response'],
        ['title' => 'Ship the change'],
        ['title' => 'Review the logs'],
    ];
}

function demoTodos(int $page): array
{
    $offset = max(0, $page - 1) * DEMO_TODO_PAGE_SIZE;

    return array_slice(demoTodoRows(), $offset, DEMO_TODO_PAGE_SIZE);
}

function demoHasMore(int $page): bool
{
    return $page * DEMO_TODO_PAGE_SIZE < count(demoTodoRows());
}

function todoItems(array $todos): array
{
    $items = [];

    foreach ($todos as $todo) {
        $items[] = li($todo['title'])->class('todo-item');
    }

    return $items;
}

function TodoList(array $todos, int $nextPage, bool $hasMore)
{
    $control = $hasMore
        ? button('Load More')
            ->type('button')
            ->id('load-more')
            ->hx_get('/todos?page=' . $nextPage)
            ->hx_target('#todos')
            ->hx_swap('beforeend')
        : span('All items loaded')->id('load-more');

    return div(
        ul(...$todoItems($todos))->id('todos'),
        $control
    )->class('todo-list');
}

function todoFragment(array $todos, int $nextPage, bool $hasMore): string
{
    $items = implode('', array_map(
        static fn (array $todo): string => li($todo['title'])->class('todo-item')->render(),
        $todos
    ));

    $control = $hasMore
        ? button('Load More')
            ->type('button')
            ->id('load-more')
            ->hx_get('/todos?page=' . $nextPage)
            ->hx_target('#todos')
            ->hx_swap('beforeend')
        : span('All items loaded')->id('load-more');

    return $items . $control->hx_swap_oob('true')->render();
}
```

The `hx-swap-oob` element is not inserted into the `<ul>`: HTMX removes it from
the normal response and swaps the existing `#load-more` control. The `<li>`
elements remain the only normal fragment content.

### 4. Define the search fragments

The input has a real `name`, a visible label, and a stable target. The endpoint
reads `q` from the query string and returns the result list only.

```php [components/SearchResult.cmp.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\div;

function SearchResult(): Call
{
    return component(__FUNCTION__);
}

register(
    SearchResult(...),
    factory: static fn () => div(Slot::value('title'))->class('search-result'),
    prepare: static fn (string $title): array => ['title' => $title],
);
```

```php [components/ResultList.cmp.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/SearchResult.cmp.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\div;

function ResultList(): Call
{
    return component(__FUNCTION__);
}

register(
    ResultList(...),
    factory: static fn () => div(Slot::raw('results')),
    prepare: static function (array $results): array {
        $items = [];

        foreach ($results as $result) {
            $items[] = SearchResult()->title($result['title']);
        }

        return ['results' => $items];
    },
);
```

```php [components/SearchBox.cmp.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ResultList.cmp.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, input, label};

function SearchBox(): Call
{
    return component(__FUNCTION__);
}

register(
    SearchBox(...),
    factory: static fn () => div(
        label('Search')->for('q')->class('search-label'),
        input()
            ->type('search')
            ->name('q')
            ->id('q')
            ->placeholder('Search...')
            ->autocomplete('off')
            ->hx_get('/search')
            ->hx_trigger('keyup changed delay:500ms')
            ->hx_target('#results'),
        div(Slot::raw('list'))->id('results')->class('search-results')
    )->class('search-box'),
    prepare: static fn (iterable|string|Stringable $list): array => ['list' => $list],
);
```

`SearchBox` accepts the `ResultList` component through the `Stringable` branch;
the list is inserted with `Slot::raw()` because it is already-rendered library
markup, not user text.

### 5. Put the routes before the page

The example assumes `components/` and `public/` are directories in the project
root. Create one front controller. Every branch that handles an HTMX request
appears before the normal page rendering and exits after writing its fragment.
`Vary: HX-Request` keeps a shared cache from mixing a document response with a
fragment response, and `Cache-Control: private` keeps the token-bearing page out
of shared caches. The path comes from `parse_url()`, so a query string is
excluded from matching.
If the application is mounted below a prefix, strip that prefix explicitly
before matching routes.

```php [public/index.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../components/CounterValue.cmp.php';
require_once __DIR__ . '/../components/Counter.cmp.php';
require_once __DIR__ . '/../components/SearchResult.cmp.php';
require_once __DIR__ . '/../components/ResultList.cmp.php';
require_once __DIR__ . '/../components/SearchBox.cmp.php';
require_once __DIR__ . '/../components/TodoList.php';

use Pure\Core\HTML;

use function Pure\HTML\{body, head, h1, html, meta, script, title};
use function Pure\Utils\renderHTML;

function requestUsesHttps(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';

    return is_string($https) && $https !== '' && strtolower($https) !== 'off';
}

function startSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => requestUsesHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfHeaders(): string
{
    return json_encode(['X-CSRF-Token' => csrfToken()], JSON_THROW_ON_ERROR);
}

function hasValidCsrf(mixed $token): bool
{
    $expected = $_SESSION['csrf_token'] ?? null;

    return is_string($expected)
        && $expected !== ''
        && is_string($token)
        && hash_equals($expected, $token);
}

function positiveInt(mixed $value, int $default = 1): int
{
    if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
        $number = (int)$value;

        return $number > 0 ? $number : $default;
    }

    return $default;
}

function countCookie(mixed $value): int
{
    if (!is_scalar($value)) {
        return 0;
    }

    $count = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 1000000],
    ]);

    return $count === false ? 0 : $count;
}

function demoSearch(string $query): array
{
    if ($query === '') {
        return [];
    }

    $items = [
        ['title' => 'Alpha component'],
        ['title' => 'Beta component'],
        ['title' => 'Gamma endpoint'],
    ];

    return array_values(array_filter(
        $items,
        static fn (array $item): bool => stripos($item['title'], $query) !== false
    ));
}

function documentHead(string $pageTitle): HTML
{
    return head(
        meta()->charset('utf-8'),
        meta()->name('viewport')->content('width=device-width, initial-scale=1'),
        title($pageTitle),
        script()->src('/assets/htmx.min.js')->defer(true)
    );
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url(is_string($requestUri) ? $requestUri : '/', PHP_URL_PATH);
$path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';

// Let the built-in server deliver existing files (assets, images) itself.
$file = realpath(__DIR__ . $path);

if (
    PHP_SAPI === 'cli-server'
    && $file !== false
    && is_file($file)
    && $file !== __FILE__
    && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)
) {
    return false;
}

$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$isHtmx = ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';

startSession();
header('Content-Type: text/html; charset=utf-8');
header('Vary: HX-Request');
header('Cache-Control: private');

if ($path === '/increment') {
    if ($method !== 'POST' || !$isHtmx) {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    if (!hasValidCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(403);
        exit;
    }

    $count = countCookie($_COOKIE['demo_count'] ?? 0) + 1;
    setcookie('demo_count', (string)$count, [
        'expires' => time() + 3600,
        'path' => '/',
        'secure' => requestUsesHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    echo CounterValue()->count($count);
    exit;
}

if ($path === '/search' && $method === 'GET' && $isHtmx) {
    $query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    echo ResultList()->results(demoSearch($query));
    exit;
}

if ($path === '/todos' && $method === 'GET' && $isHtmx) {
    $page = positiveInt($_GET['page'] ?? null);
    echo todoFragment(demoTodos($page), $page + 1, demoHasMore($page));
    exit;
}

if ($path === '/') {
    $count = countCookie($_COOKIE['demo_count'] ?? 0);
    echo renderHTML(
        html(
            documentHead('Counter'),
            body(Counter()->count($count)->csrfHeaders(csrfHeaders()))
        )
    );
    exit;
}

if ($path === '/search') {
    echo renderHTML(
        html(
            documentHead('Search'),
            body(SearchBox()->list(ResultList()->results([])))
        )
    );
    exit;
}

if ($path === '/todos') {
    $page = positiveInt($_GET['page'] ?? null);
    echo renderHTML(
        html(
            documentHead('Todos'),
            body(TodoList(demoTodos($page), $page + 1, demoHasMore($page)))
        )
    );
    exit;
}

http_response_code(404);
echo renderHTML(
    html(
        documentHead('Not found'),
        body(h1('Not found'))
    )
);
```

The counter and search endpoint branches write only `CounterValue` or
`ResultList`; the list branch writes only list items and its OOB control. Only
normal page branches and the 404 branch call `renderHTML()`.

Run the example with `php -S localhost:8000 -t public public/index.php` and open
`http://localhost:8000/`. The `cli-server` branch hands existing files under
`public/` — such as `assets/htmx.min.js` — back to the built-in server; a
production web server serves them directly.

## Security and state boundaries

- `POST /increment` is a state change, so it requires both `HX-Request: true`
  and a session-bound `X-CSRF-Token`. `hash_equals()` compares the token; a
  cookie alone is not a CSRF defense.
- The session cookie is configured before `session_start()` with `HttpOnly`,
  `SameSite=Lax`, and `Secure` when the request is HTTPS. On plain local HTTP,
  `Secure` must be false or the browser will not return the session cookie. If
  a proxy terminates TLS, configure trusted proxy detection before choosing
  this flag.
- `demo_count` is deliberately a non-sensitive demo cookie. A browser can edit
  it, so never use its value for authorization or authoritative state. Store
  real state in a server-side session or database and apply the same cookie
  flags to the session that owns it.
- `Slot::value()` escapes text and attribute values, but `Slot::raw()` is a
  trust boundary. Pass it only markup produced by your application, validate
  URLs before putting them in `href` or `src`, and keep dynamic tag names,
  event handlers, and CSS out of untrusted input.
- Keep the CSRF token and session cookie out of client-visible storage. The
  example places the token in a request header rendered into the page, while
  `HttpOnly` keeps the session cookie inaccessible to JavaScript.

## Next steps

- [HTMX documentation](https://htmx.org/docs/)
- [Components](/guide/components)
- [Events and request boundaries](/guide/events)
- [Security basics](/guide/basic-usage)
