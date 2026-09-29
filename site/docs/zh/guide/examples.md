---
title: 示例
description: 运行 PurePHP 的 bootstrap、event-counter 与 XML 示例，比较严格产物、plain 视图和静态 cover 路由。
---

# 示例

仓库把可运行示例放在 `examples/`。它们使用同一套组件、编译与 plain 视图路径，
方便比较小型生产 Shape 与无依赖视图。

## 先从片段开始

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{a, div};

div(
    'Hello ',
    a('PHP')->href('https://www.php.net')
)->class('container')->print();
```

组件会增加类型化 prop 契约与不含数据的 Shape：

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(): Call
{
    return component(__FUNCTION__);
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

echo Card()->title('Card Title')->content('Card Content');
```

## children、列表与按钮

这个单元在一个 Shape 中展示三条常见数据路径：通过保留 raw Slot
传入 children、用 `Slot::each()` 重复记录，以及用 value Slot 设置按钮属性。

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button, div, h2, li, ul};

function PricingCard(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(PricingCard(...),
    factory: static fn () => div(
        Slot::raw('children'),
        h2(Slot::value('type'))->class('card-title'),
        ul(Slot::each('features', li(Slot::value('value')))),
        button(Slot::value('text'))->class(Slot::value('class'))
    )->class('card'),
    prepare: static function (string $type, array $features, string $text, string $class): array {
        return ['type' => $type, 'features' => $features, 'text' => $text, 'class' => $class];
    }
);

echo div(
    PricingCard(h2('Pro'))
        ->type('Free')
        ->features([['value' => '10 users'], ['value' => '2 GB']])
        ->text('Sign up for free')
        ->class('btn btn-lg btn-block btn-outline-primary')
);
```

children 传给调用函数，列表可用 `Slot::each()` 绑定；组件 `Call` 实现了
`Pure\Core\Markup`，因此可以像标签一样嵌套。完整词汇请先看[快速开始](/zh/guide/getting-started)，
再看[组件](/zh/guide/components)。

## 准备仓库

示例不包含在 Composer 安装包内，请先克隆仓库，并在仓库根目录执行下面的命令。克隆里
没有 `vendor/bin/pure`——Composer 只为已安装的依赖包创建这个代理——所以请使用
`bin/pure`：

```bash
git clone https://github.com/YonLD/purephp.git
cd purephp
composer install
php bin/pure compile --plain examples/bootstrap
php bin/pure compile --plain examples/event-counter
php bin/pure compile --plain examples/xml
```

用 `--list` 查看发现的单元。输出类型是 `(component)`、`(shape)` 与
`(template)`；页面不是单独的文件类型：

```bash
php bin/pure compile --list examples
```

## Bootstrap MVC 示例

`examples/bootstrap` 是一个小型 MVC 应用。Controller 保持轻量，DAO 读取数据，
Service 把记录转换为 bindings，组件单元负责自己的标记。`features` 与 `pricing`
页面同时有严格产物和 plain 视图；cover 页面是静态标记，按设计没有这两种变体。

| 路由 | 渲染内容 |
| --- | --- |
| `/cover` | 静态 `views/cover.php`；不需要编译。 |
| `/pure/features` | 页面函数与 `*.pure.php` 组件产物。 |
| `/plain/features` | 组件 bindings 渲染后加载 `features.plain.php`。 |
| `/pure/pricing` | 页面函数与 `*.pure.php` 组件产物。 |
| `/plain/pricing` | 组件 bindings 渲染后加载 `pricing.plain.php`。 |

在仓库根目录启动 router：

```bash
php -S localhost:8000 -t examples/bootstrap/public \
    examples/bootstrap/public/index.php
```

访问 `http://localhost:8000/cover`，再比较 `/pure/features` 与 `/plain/features`、
`/pure/pricing` 与 `/plain/pricing`。未知路径会返回列出全部五个路由的 404 页面。

plain loader 会先把数据提取为局部变量，再 require 生成的视图。`Call` binding
会在加载视图前转成标记，因此 plain 文件本身在渲染时不需要 PurePHP。

## Event counter 示例

`examples/event-counter` 展示一个初始值由 `random_int(0, 100)` 随机生成、带浏览器端
`+` / `-` 控件的小页面，同样有严格与 plain 两种变体：

| 路由 | 渲染内容 |
| --- | --- |
| `/` 或 `/index.php` | 重定向到 `/plain`。 |
| `/pure` | 页面函数与 `counter.pure.php`。 |
| `/plain` | `counter.plain.php`，视图文件不调用库。 |

启动方式：

```bash
php -S localhost:8000 -t examples/event-counter/public \
    examples/event-counter/public/index.php
```

打开 `http://localhost:8000/pure` 或 `/plain`，再点击计数器按钮。JavaScript 与
CSS 是 `public/` 下的静态文件；router 会把它们交还给内置服务器直接提供。

## XML 示例

`examples/xml` 展示 XML 根节点、嵌套 item Shape、可选字段以及 `#[Template]`
构建器，并覆盖 `XML::state` 与 `XML::address` 分支。响应类型是 XML，而不是 HTML：

| 路由或命令 | 行为 |
| --- | --- |
| `/` 或 `/index.php` | 重定向到 `/plain`。 |
| `/pure` | 页面函数与 `xml.pure.php`。 |
| `/plain` | 渲染为文档的 `xml.plain.php`。 |
| `php examples/xml/write.php` | 写出 `examples/xml/example.xml` 并打印字节数。 |

Web 版本启动方式：

```bash
php -S localhost:8000 -t examples/xml/public examples/xml/public/index.php
```

也可以在仓库根目录写文件：

```bash
php examples/xml/write.php
```

XML 声明由 `renderXML()` 提供，模板只描述树结构。`AddressShape()` 展示
`Slot::each()` 列表与 `Slot::if()` 可选 city。

## 对比严格与 plain 输出

对于普通数据，生成的 plain view 与严格产物逐字节一致；当根节点是 HTML 或 XML
文档时还会包含文档声明。plain 路径适合只部署 `public/` 与 `views/`、不安装
PurePHP 的环境。它是基于 include 的视图，不是第二套模板语言。

路由缺失、产物过期或片段被转义时，请看[故障排查](/zh/guide/troubleshooting)。
发布相关的重建步骤见[升级](/zh/guide/upgrading)。
