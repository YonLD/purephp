# PurePHP 与 HTMX 集成

**前置**：[组件](/zh/guide/components)；**本页**：用服务端 fragment、路由和安全状态变更实现 HTMX 交互。

PurePHP 在服务端渲染 HTML，HTMX 把返回的 HTML 换入页面。保持边界清晰：普通路由返回
完整文档，HTMX 路由只返回目标所需的 fragment。

::: tip Fragment 与编译 Shape
`hx-*` 是数据无关标签树上的普通属性。把它们放进编译 Shape，再用请求数据渲染同一个
Shape。下面的示例使用注册组件，让 fragment 契约保持可见。
:::

## 快速开始

### 1. 安装并锁定 HTMX

先安装 PurePHP：

```bash
composer require yonld/purephp
```

本指南锁定 HTMX **2.0.11**。使用 CDN 时，必须固定精确文件及其公布的 SRI：

```html
<script
  src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.11/dist/htmx.min.js"
  integrity="sha384-2OatzQy1H+Zd/IIrjr1TcuDGqLXeHhbooAyJY1KdQMKnr4LZ22k31GBLdYKHmVjg"
  crossorigin="anonymous"
></script>
```

使用本地依赖时，把同一版本写入 `package.json` 和 `package-lock.json`，再复制精确文件到
Web 根目录：

```bash
npm install --save-exact htmx.org@2.0.11
mkdir -p public/assets
cp node_modules/htmx.org/dist/htmx.min.js public/assets/htmx.min.js
```

用同源 URL 加载：

```html
<script src="/assets/htmx.min.js" defer></script>
```

SRI 只适用于 CDN 提供的精确字节。自托管时应使用锁定版本的 npm lockfile 和项目自己的资源
校验，不要把 CDN hash 套到另一个构建产物上。下面的 PHP 前端控制器使用本地 URL。

### 2. 定义计数器 fragment

计数器页面包含按钮和值。值拆成独立单元，因此 POST 端点可以只返回它，而不返回周围的页面。

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

`hx_swap="outerHTML"` 会用端点返回的新 `<p>` 替换 `<p id="counter">`。端点绝不 echo
周围的页面。

### 3. 添加真正的分页

下面的列表从第 1 页开始。每次响应只追加 `<li>`，并使用 out-of-band swap 把 Load More
控件替换成下一页的控件。这是真正的分页：下一个 URL 来自当前页面；最后一页不足整页时会移除按钮。

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

`hx-swap-oob` 元素不会插入 `<ul>`：HTMX 会把它从普通响应中移除，再替换现有的
`#load-more` 控件。`<li>` 仍是唯一会正常追加的 fragment 内容。

### 4. 定义搜索 fragment

输入框有真实的 `name`、可见 label 和稳定的目标。端点从 query string 读取 `q`，并只返回
结果列表。

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

`SearchBox` 通过 `Stringable` 分支接收 `ResultList` 组件；列表已经由库渲染成标记，
所以用 `Slot::raw()` 插入，而不是把它当作用户文本。

### 5. 把路由放在页面之前

示例假定 `components/` 和 `public/` 是项目根目录下的目录。创建一个前端控制器。每个处理
HTMX 请求的分支都位于普通页面渲染之前，并在写出 fragment 后立即退出。
`Vary: HX-Request` 可避免共享缓存把文档响应和 fragment 响应混在一起，
`Cache-Control: private` 则避免把带 token 的页面放进共享缓存。路径来自
`parse_url()`，因此匹配时不会包含 query string。如果应用挂在某个路径前缀下，应在匹配
路由前显式去掉该前缀。

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

// 已存在的文件（assets、图片）交还给内置服务器直接提供。
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

计数器和搜索端点分支只写出 `CounterValue` 或 `ResultList`；列表分支只写出列表项和 OOB
控件。只有普通页面分支和 404 分支调用 `renderHTML()`。

用 `php -S localhost:8000 -t public public/index.php` 启动示例并打开
`http://localhost:8000/`。其中的 `cli-server` 分支会把 `public/` 下已存在的文件
（例如 `assets/htmx.min.js`）交还给内置服务器；生产环境的 Web 服务器会直接提供它们。

## 安全与状态边界

- `POST /increment` 是状态变更，因此同时要求 `HX-Request: true` 和绑定 session 的
  `X-CSRF-Token`。`hash_equals()` 负责比较；只依赖 cookie 不能防御 CSRF。
- `session_start()` 前设置 session cookie 的 `HttpOnly`、`SameSite=Lax`，HTTPS 请求再启用
  `Secure`。本地明文 HTTP 必须关闭 `Secure`，否则浏览器不会返回 session cookie。如果由
  代理终止 TLS，应先配置可信的代理检测，再决定这个标志。
- `demo_count` 明确只是非敏感演示 cookie。浏览器可以修改它，绝不能用它做授权或权威状态；
  真实状态应放在服务端 session 或数据库，并为拥有它的 session 使用同样的 cookie 标志。
- `Slot::value()` 会转义文本和属性值，但 `Slot::raw()` 是信任边界。只传应用自己生成的标记，
  把 URL 放进 `href` 或 `src` 前先验证，并让不可信输入远离动态标签名、事件处理器和 CSS。
- 不要把 CSRF token 和 session cookie 放进客户端可见存储。示例把 token 放在渲染进页面的请求
  header 中，而 `HttpOnly` 让 JavaScript 无法读取 session cookie。

## 下一步

- [HTMX 文档](https://htmx.org/docs/)
- [组件](/zh/guide/components)
- [事件与请求边界](/zh/guide/events)
- [安全基础](/zh/guide/basic-usage)
