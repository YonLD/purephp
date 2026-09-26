# 快速开始

**前置**：PHP 8.1+、Composer；**本页**：安装 PurePHP，直接运行组件，并把同一组件接入真实应用入口。

本指南带你走一条最小但完整的路径：安装包，先渲染一次标签树，再定义一个类型化组件，
最后从应用入口调用它。组件是一个单元，包含调用函数和数据无关的模板；`Slot` 占位符通过类型化
的 `prepare()` 钩子接收调用时传入的值。

## 环境要求

- PHP 8.1 或更高版本
- Composer

## 安装

### 安装包

在项目根目录运行：

```bash
composer require yonld/purephp
```

### 验证安装

在项目根目录创建 `test.php`：

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

运行：

```bash
php test.php
```

输出是一个 HTML 片段。这个即时渲染 API 适合做第一次检查；下面的应用会使用组件和前端入口。

## 创建第一个应用

### 1. 创建项目

```bash
mkdir my-purephp-app
cd my-purephp-app
composer require yonld/purephp
mkdir -p components public
```

请保持 `composer.json`、`vendor/`、`components/` 和 `public/` 这个目录布局。下面的路径都基于它。

### 2. 定义组件

创建 `components/Card.cmp.php`：

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

`Slot::value()` 标记一个文本位置。`prepare()` 的参数就是链式调用接受的类型化 props，
返回的数组会绑定到模板。这个文件可以被应用 `require`：CLI 守卫只会在直接执行该文件时运行，
因此直接运行 smoke test 和前端入口可以共用同一个单元。

### 3. 直接运行组件

把单元作为独立脚本运行：

```bash
php components/Card.cmp.php
```

预期输出：

```html
<div class="card"><h2>Title</h2><p>Content</p></div>
```

这是组件 smoke test，不是完整 HTML 文档。它验证了 autoload、注册、prop 契约和渲染器可以一起工作。

### 4. 添加真实应用入口

创建 `public/index.php`：

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

入口负责提供请求数据、调用组件并补上文档外壳。在项目根目录启动 PHP 开发服务器：

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

打开 `http://127.0.0.1:8000/?title=Hello&content=From+the+entry+point`。
组件仍然是同一个单元，变化的只有调用方和文档外壳。

## 下一步

按这个顺序学习：

1. [基本用法](/zh/guide/basic-usage)——学习标签 API 和片段渲染。
2. [核心概念](/zh/guide/concepts)——理解标签树、Shape、Slot 与组件。
3. [组件](/zh/guide/components)——组合单元并定义页面级 prop 契约。
4. [HTMX](/zh/guide/htmx)——用 HTML fragment 实现服务端交互。
5. [Tailwind CSS](/zh/guide/tailwindcss)——用锁定版本的 CSS 构建为组件添加样式。
6. [编译渲染](/zh/guide/compiled)与[产物与部署](/zh/guide/artifacts)——加入缓存、产物和 CI 检查。

生产环境应保持前端入口轻薄，在边界验证请求数据，并使用 `pure check` 以及
[产物与部署](/zh/guide/artifacts#契约检查)中的部署步骤。
