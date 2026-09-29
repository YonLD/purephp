---
title: 故障排查
description: 按症状排查安装、Slot、组件、产物、缓存和示例问题。
---

# 故障排查

先找到与现象匹配的条目。示例假设你在项目根目录执行命令，并已安装 Composer。

## 安装与命名空间

### `Class "Pure\..." not found` 或 `Call to undefined function Pure\...()`

使用库之前先加载 Composer 自动加载器，并确认运行时满足项目声明的 PHP 下限：

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{div, h1};

echo div(h1('Hello'))->render();
```

项目要求 PHP 8.1 或更新版本。`div()` 等辅助函数需要 `use function`；
`Pure\Core\Slot`、`Pure\Component\Call` 等类需要 `use`。复制的示例若省略
自动加载器，就不是独立可运行的脚本。

### 找不到 `vendor/bin/pure`

在通过 Composer 安装了本包的应用里，`composer install` 会创建 `vendor/bin/pure`
代理，在项目根目录执行它即可：

```bash
composer install
vendor/bin/pure --help
```

在**本仓库的克隆**里没有 `vendor/bin/pure`：Composer 只为已安装的依赖包创建这个
代理，请在仓库根目录改用 `php bin/pure`。

示例同样依赖根目录的 `vendor/autoload.php`；从其它工作目录启动文件不会改变
这个要求。

### `no *.shape.php or *.cmp.php files found in '...'`

两个命令都只按文件后缀发现单元，此外没有任何别的规则：组件单元是 `*.cmp.php`，
低层模板是 `*.shape.php`。把文件命名为 `Card.php` 在运行时完全正常——后缀只是
编译器和检查器找到它的方式——所以项目在第一次 `pure compile` 之前看上去都是健康的。

重命名文件，或者直接传真实路径，这样错误信息会指名文件：

```bash
mv components/Card.php components/Card.cmp.php
vendor/bin/pure compile components
```

文件确实存在但后缀不对，直接传路径时会报下面这条；目录里只有错误的后缀时报上面
那条 `no ... files found`。两者是同一个错误的两面。

```text
'...' is not a *.shape.php or *.cmp.php file.
```

## Slot 与绑定错误

### `MissingSlotException: slot '...' is required but was not provided`

模板读取了必填键，但渲染数据里没有它。请在调用处补上键；如果缺失确实合法，
把 Slot 标为可选：

```php
Slot::value('title')
Slot::value('title')->required(false)
Slot::value('title')->default('Untitled')
```

必填的 value/raw Slot 也会拒绝显式 `null`。请传入真正的文本，或改用可选 Slot；
不要用 `null` 表示“缺失”。Slot 参考见 [编译 API](/zh/api/compile#slot-类型)
与 [Props 与 Slot](/zh/guide/props)。

### child 或 each Slot 的值被拒绝

`Slot::child()` 有结构约束：child 作用域必须是数组。each 的每一项同样是作用域，
唯一例外是条目 Shape 只渲染一个 Slot —— 此时该项可以直接是该 Slot 的值，
字符串列表无需再包一层。条目 Shape 读取多个 Slot，或唯一的 Slot 是嵌套的
`Slot::child()` / `Slot::each()` 时，每一项仍然必须是数组：

```php
$rows = array_map(
    static fn (array $record): array => ['title' => $record['title']],
    $records
);

echo $page(['rows' => $rows]);
```

报错信息会列出条目 Shape 读取的键，照着它构造即可。已经渲染好的字符串列表应放入
`Slot::raw()`，而不是 `Slot::each()`。

### 出现 `unknown prop`、`missing prop` 或 `did you mean`

存在 `prepare()` 钩子时，钩子参数就是完整的链式 prop 契约。按精确名称传入所有
必填参数，并删除未知 setter：

```php
register(Card(...),
    factory: static fn () => div(...),
    prepare: static function (string $title, string $class = 'card'): array {
        return ['title' => $title, 'class' => $class];
    }
);

echo Card()->title('Welcome')->class('card');
```

没有 `prepare()` 时，setter 名称本身就是 bindings。

看到哪一条消息取决于错在哪。未知 setter 会先被报出，并附带一条针对"只差一次编辑"
的那个 prop 的 `did you mean`——所以必填 prop 拼错时读到的是
`unknown prop 'titel' (did you mean 'title'?)` 而不是 `missing prop`；同一次调用里的
每个未知 prop 都有各自的建议。等到报 `missing prop` 时，setter 名称是对的，只是值没传。

修改单元后运行 `vendor/bin/pure check components`，尽早发现不匹配：它报的是同一份
契约，并带上出问题的那个 `->prop(...)` 所在行号。

### 子内容消失，或提示模板没有 `children` Slot

children 要传给调用函数，而不是调用 `children()` setter。模板必须显式读取保留
Slot：

```php
function Button(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Button(...), static fn () =>
    button(Slot::raw('children'))->class('btn')
);

echo Button('Save');
```

没有 `Slot::raw('children')` 的模板按设计会拒绝非空 children。反过来也会悄悄丢失
内容：调用函数没有声明 `mixed ...$children` 时，调用传入的参数会被忽略，因为 PHP 允许
向用户函数多传参数。模板确实读取了该 Slot 而 children 仍然缺失时，声明它并转发给
`component()`。`pure check` 检查的是 `prepare()` 与 `->prop()` 链，不读调用函数，因此
不会报告这种情况。

## 转义与标记

### 标记被当成转义文本显示

普通字符串子节点就是文本。如果内容确实要作为标记输出，请把
`Pure\Core\Markup` 值（例如 `Raw::of($trustedHtml)` 或另一个组件 `Call`）送入 raw
Slot：

```php
use Pure\Core\Raw;

return ['body' => Raw::of($trustedHtml)];
```

用户文本使用 `Slot::value()`。不要因为参数标了 `#[Trusted]` 就把请求数据当成可信
标记；该注解只表达信任边界，不会替你清理输入。

## 产物、缓存与性能

### 输出是旧的，或产物提示运行 `pure compile`

修改源单元或升级库后重新生成产物：

```bash
vendor/bin/pure compile components
vendor/bin/pure compile --plain components
vendor/bin/pure compile --check --plain components
```

产物必须不早于源文件，并返回 `Pure\Compile\Renderer`。缓存条目过期时，用
`Compile::clearCache()` 或只删除库自己管理的缓存目录；不要手改生成文件。

### 每个请求都在重新构建 Shape

`static` 变量的生命周期只有一个 PHP 进程。PHP-FPM 下应在 bootstrap 中启用磁盘
渲染器缓存，或随应用发布新产物：

```php
use Pure\Compile\Compile;

Compile::cachePath(__DIR__ . '/var/cache/purephp');
```

缓存目录应私有且位于 Web 根目录之外。`Compile::flush()` 只清理内存中的渲染器，
不会删除缓存文件。详见[编译渲染](/zh/guide/compiled)与[产物与部署](/zh/guide/artifacts)。

### `pure compile --list` 出现不熟悉的单元类型

列表描述的是发现的文件：

- `(component)` 是已注册的 `*.cmp.php` 单元；
- `(shape)` 是独立的 `*.shape.php` 模板；
- `(template)` 是标记了 `#[Template]` 的函数。

没有 `(page)` 类型。页面可以是根节点为文档根的组件单元，也可以由调用方渲染独立
模板。

### plain 视图缺失或仍然调用 PurePHP

显式生成它：

```bash
php bin/pure compile --plain examples/bootstrap
```

`*.plain.php` 只包含标记与原生 PHP。加载器必须先把 bindings 提取为局部变量再
`require`；参见[示例](/zh/guide/examples)与[产物与部署](/zh/guide/artifacts#无依赖视图注意事项)。

## 组件与 PHPStan 边界

### PHPStan 报 `unknown component 'X'`

`Pure\StaticAnalysis\UnknownComponentRule` 会在完整项目分析中把字面量组件调用
和注册项进行比较。请在被分析项目中用 `register(Icon(...))` 注册单元，或使用与
调用函数一致的名称。路径和 `component(__FUNCTION__)` 会按文件/函数上下文解析。

如果规则没有生效，请在自己的 `phpstan.neon` 中注册它，配置见
[组件 API](/zh/api/component#phpstan-集成)。该规则是项目级检查，请分析整个项目而不是
单个文件：只有所有文件都分析完成后，注册项才完整。

`pure check` 还会加载发现的单元文件并调用 factory 来校验契约，因此只应对可信项目代码运行，
并保持 factory 没有副作用。

### `Registry` 标为 `@internal`

`Pure\Component\Registry` 是公开 `register()` 与 `component()` 函数使用的实现注册表。
应用代码应使用这些辅助函数，不要依赖 `Registry` 的方法签名。公开组件入口汇总见
[组件 API](/zh/api/component)。

## HTTP 与示例

### HTMX 响应在片段前包含完整页面

HTMX endpoint 只应返回客户端请求的片段。按请求方法分支并只输出一次替换标记；
不要先打印完整文档再追加片段。对比两种渲染路径时，保持 `/pure` 与 `/plain` 路由
彼此独立。

### 本地示例返回 404

从示例文档指定的目录启动，并使用对应的 front controller：

```bash
php -S localhost:8000 -t examples/bootstrap/public examples/bootstrap/public/index.php
php -S localhost:8000 -t examples/event-counter/public examples/event-counter/public/index.php
php -S localhost:8000 -t examples/xml/public examples/xml/public/index.php
```

路由表见[示例](/zh/guide/examples)。404 响应也会列出当前示例支持的路由。

## 快速诊断清单

1. 确认已加载 `vendor/autoload.php`，且 PHP 为 8.1 或更新版本。
2. 运行 `vendor/bin/pure compile --list <path>`，确认实际发现的单元类型。
3. 调试生成结果前先运行 `vendor/bin/pure check <path>`。
4. 修改源文件或依赖后重建 `*.pure.php` 与 `*.plain.php`。
5. 对照模板必填 Slot 检查数据键；显式 `null` 不能代替必填值。
6. 先用[示例](/zh/guide/examples)中的最小复现，再逐项加入功能。
