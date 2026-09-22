# PurePHP 与 HTMX 集成

**前置**：[组件](/zh/guide/components)；**本页**：用 HTMX 做无 JavaScript 的动态交互。

PurePHP 和 HTMX 构成强大的组合，让你能够构建动态、响应式的用户界面，同时保持 PHP 后端简洁。

::: tip HTMX 片段适合编译 Shape
HTMX 片段天然适合编译 Shape：`hx-*` 属性是普通的静态标签属性，因此在 Shape 上只设置一次，端点用请求数据渲染同一个 Shape。参见[编译渲染](/zh/guide/compiled)。
:::

## 为什么选择这个组合？

- **PurePHP**：提供组件化的 PHP 模板渲染
- **HTMX**：提供无 JavaScript 的动态交互能力
- **完美匹配**：PurePHP 处理服务端渲染，HTMX 处理客户端交互

## 快速开始

### 1. 安装依赖

```bash
composer require yonld/purephp
```

然后在 HTML 中引入 HTMX：

```html
<script src="https://unpkg.com/htmx.org@2.0.4"></script>
```

### 2. 创建动态组件

计数器文本是绑定的 Slot；端点用新的计数渲染同一个 `CounterValue()` 组件。每个组件都是自己的单元：

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

// 渲染页面
echo Counter()->count(0);

// 处理 HTMX 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/increment') {
    $count = (int)($_COOKIE['count'] ?? 0) + 1;
    setcookie('count', $count);

    echo CounterValue()->count($count);
    exit;
}
```

### 3. 无限滚动列表

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

// 处理 HTMX 请求
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], '/todos') === 0) {
    $page = (int)($_GET['page'] ?? 1);
    $todos = getTodos($page); // 获取待办事项列表

    foreach ($todos as $todo) {
        echo li($todo['title'])->class('todo-item');
    }
    exit;
}
```

### 4. 实时搜索

结果列表是一个组件；每个结果标题都用 `Slot::value()` 绑定，端点用搜索结果渲染列表组件：

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

// 用空结果列表渲染页面
echo SearchBox()->list(ResultList()->results([]));

// 处理 HTMX 请求
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['REQUEST_URI'] === '/search') {
    $query = $_GET['q'] ?? '';
    $results = searchItems($query); // 搜索条目

    echo ResultList()->results($results);
    exit;
}
```

## 下一步

- [HTMX 文档](https://htmx.org/docs/)
- [PurePHP 组件指南](/zh/guide/components)
- [PurePHP 事件处理](/zh/guide/events)
