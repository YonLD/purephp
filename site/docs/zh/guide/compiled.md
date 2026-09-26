# 编译渲染

**前置**：[组件](/zh/guide/components)；**本页**：组件模板的编译、运行时缓存与性能。

编译渲染把不含数据的 **Shape**——组件的模板——转换为扁平的 PHP 渲染器。静态标记在编译期只转义一次，并作为字面量字符串输出，因此渲染页面的开销仅比字符串拼接加上动态值转义略高——与编译型模板引擎持平。

组件的工厂返回一棵带 `Slot` 占位符的裸标签树；注册表会把它包装成 `Shape`——对内联树，
`Compile::shape()` 做的正是同一个包装。模板**每个进程只构建一次**——长驻 worker、CLI
进程，或任何在请求之间保留 PHP 状态的运行时。`opcache.preload` 可以让代码常驻内存，但
不会跨请求保留 static Shape 状态。标准 PHP-FPM 下每个请求都是全新的，因此请启用磁盘缓存
（见[缓存](#缓存)），或用预编译产物代替运行时编译（见[产物与部署](/zh/guide/artifacts)）。

## Shape、Slot、Renderer

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, li, span, ul};

// Shape 就是普通标签树，只是把数据换成 Slot 占位符。
$item = Compile::shape(li(Slot::value('title')));

$root = Compile::shape(
    div(
        h1(Slot::value('heading')),
        ul(Slot::each('items', $item))
    )->class('card')
);

// 渲染只负责绑定普通数据。
echo $root([
    'heading' => 'Users',
    'items' => [
        ['title' => 'Ada'],
        ['title' => 'Grace']
    ],
]);
```

对同一棵树，输出与 `Tag::render()` **逐字节一致**，因为两条路径共用同一份转义实现。

| 对象 | 含义 |
| --- | --- |
| `Shape` | 不含数据的树；`__invoke($data)`、`compile()`、`id()`、`print($data)`、`save($path, $data)` |
| `Renderer` | 编译后的渲染器；`render($data)`、`save($path, $data)`，以及只读属性 `source` / `id` / `slots` |
| `Slot` | 数据的占位符，在渲染时绑定 |

模板里可用的 Slot 一览（`Slot::value()`、`Slot::raw()`、`Slot::child()`、`Slot::each()`、
`Slot::if()`）以及修饰符、值转换与缺失数据的完整语义，见
[Props 与 Slot](/zh/guide/props#slot-参考)，本页不再重复。

## 作用域与缺失数据

`Slot::child()` 与 `Slot::each()` 会创建嵌套数据作用域；在其中，Slot 针对该作用域解析。
缺失必填键会抛出带完整路径的 `Pure\Core\MissingSlotException`，错误信息会建议最接近的已提供
键名或列出该作用域实际提供的键；可选数据请使用 `->default($value)` 或 `->required(false)`——
详见[缺失数据](/zh/guide/props#缺失数据)。

`Slot::if()` 的分支共享当前作用域，因此下面这样写可以自然工作：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{li, span};

$item = Compile::shape(
    li(
        Slot::value('name'),
        Slot::if('admin', span('(admin)'))
    )
);
```

## 组件

组件的模板——带调用函数、惰性工厂与 `prepare()` 钩子的 `*.cmp.php` 单元（完整讲解见
[组件](/zh/guide/components)）——走的是同一条管线：工厂每个编译 generation 运行一次，
树被包装成 Shape，请求复用的是编译后的渲染器（PHP-FPM 场景见[缓存](#缓存)）：

```php [components/Card.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(): Call
{
    return component(__FUNCTION__);
}

register(Card(...),
    factory: static fn () =>
        div(
            h2(Slot::value('title')),
            p(Slot::value('content'))
        )->class('card'),
    prepare: static function (
        string $title,
        string $content
    ): array {
        return [
            'title' => $title,
            'content' => $content
        ];
    }
);

echo Card()->title('Title')->content('Content');
```

模板内部：嵌套 Shape 用 `Slot::child()`，列表用 `Slot::each()`，可选/条件标记用
`Slot::if()`，已渲染的子组件经 `Slot::raw()` 注入。

### 列表

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{li, ul};

$row = Compile::shape(li(Slot::value('label')));

$shape = Compile::shape(ul(Slot::each('rows', $row)));
$shape([
    'rows' => [
        ['label' => 'a'],
        ['label' => 'b']
    ]
]);
```

## 缓存

默认情况下，编译后的渲染器只存在于内存中，这在请求之间保留状态的长驻 worker 中收益最大。标准 PHP-FPM 下，Shape 树会在每个请求中重建、渲染器会被重新生成——这比即时渲染更慢——因此请启用磁盘渲染器缓存，直接加载生成的代码而不是重新生成：

```php
<?php

use Pure\Compile\Compile;

// 在引导阶段执行一次
Compile::cachePath(__DIR__ . '/var/cache/purephp');
```

- `Compile::cachePath($dir)` 开启磁盘渲染器缓存；传 `null` 关闭（默认）。
- 缓存文件以 `Shape::id()` 为内容寻址；Shape 或 PHP 次版本变化都会生成新文件。
- 写入是原子的（临时文件 + 重命名），因此并发 worker 是安全的。
- 缓存文件是普通 PHP，对 opcache 友好。`cachePath()` 会以 `0700` 创建缺失目录，拒绝
  组或其他用户可写的权限；当 POSIX 属主 API 可用时，也会拒绝属主不符的目录。它不会检查
  路径是否位于 Web 根目录之外，因此请把缓存放在文档根目录之外的专用目录；不要直接指向
  `/tmp` 这类共享位置。
- `Compile::clearCache()` 会删除由本库写入的文件。
- `Compile::flush()` 会使内存中的渲染器失效（在部署后的长驻 worker 中很有用）。

要发现每个请求都重新构建（而不是被记忆化）的 Shape，并让输出中看不出来的问题被报告出来，请启用开发守卫：

```php
<?php

use Pure\Compile\Compile;

Compile::guard(true);           // 或设置 PURE_COMPILE_GUARD=1
```

它在单个进程内每个对象只发出一次 `E_USER_WARNING`，覆盖三类问题：

- 同一调用点在一个进程内第 20 次调用 `Compile::shape()` 时警告每请求重建，建议改为
  `static $shape ??=` 模式；
- 模板从未读取的 binding 会被报告（附 `did you mean` 建议），拼错的键名不会被静默忽略；
- 与标准属性名只差一个字符的属性方法（`->clas(...)`、`->hreff(...)`）会告警，而不是静默变成
  没人注意的自定义属性。

生产环境请启用磁盘缓存并构建预编译产物，让请求加载已编译的渲染器——见
[产物与部署](/zh/guide/artifacts)。

## 性能

本节是整份文档唯一的规范性能实测参考。下表快照来自
[commit `c9b33e3` 的 `bench/README.md`](https://github.com/YonLD/purephp/blob/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench/README.md#recorded-numbers)，
基准源码也固定在[同一 commit](https://github.com/YonLD/purephp/tree/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench)。
记录使用 `bench/compare.php`，在 AMD Ryzen 5 7500F、Linux、PHP 8.4.24 CLI
（NTS，opcache 8.4.24）上运行 200 行（约 604 个渲染元素）、3000 次迭代，记录日期为
2026-09-16。

| 路径 | 无 opcache | Opcache | Opcache + JIT |
| --- | ---: | ---: | ---: |
| 构建树 + `Tag::render()` | 631.0 µs | 608.3 µs | 433.8 µs |
| 编译 Shape + 数据 | 141.7 µs | 135.2 µs | 107.9 µs |
| 仅渲染（复用树） | 224.2 µs | 212.4 µs | 163.5 µs |
| 编译静态树（字面量） | 0.1 µs | 0.1 µs | 0.2 µs |
| 端到端加速 | 4.5× | 4.5× | 4.0× |

这些数字代表一次真实记录，不是对其它 CPU 或 PHP 构建的承诺。同一源码快照还记录了产物加载
（`bench/artifact.php`）、渲染器缓存（`bench/cache.php`）、组件加载
（`bench/registry.php`）与完整页面（`examples/bootstrap/bench.php`）。选择 worker、运行时缓存
或预编译产物前，应在目标部署上运行相应脚本。

同一快照中的支撑数据：预编译产物首次 `require` 需 38–67 µs，热加载约 25 µs，每次渲染
1.4–2.5 µs（`bench/artifact.php`）；渲染器缓存冷启动约 0.78 ms，热读取约 0.26 ms
（`bench/cache.php`）；开启 opcache 后，22 个组件产物合计只需 10–14 µs 加载
（`bench/registry.php`）；示例页面作为页面函数约 104 µs、作为 plain 视图约 20 µs
（`examples/bootstrap/bench.php`）。校验内容比编译更贵：对每个单元做哈希约 7 µs/文件，
读取产物头部约 4 µs，而用 opcache 加载产物约 0.5 µs。

## 可信标记与部署

`Slot::raw()` 与组件 `Markup` 子节点会原样输出，应只接收可信输入；请校验 URL 协议和活动
属性，页面有意包含脚本或样式时使用浏览器 Content Security Policy。编译文本/值 Slot 的
转义保护语法，但不是 URL 或用户标记的清理器。预编译的 `*.pure.php` 是可执行 PHP，应从
可信源码构建并限制写入权限。

## 限制

- 标签名不能依赖数据：Shape 始终使用相同的标签。结构变化请使用 `Slot::if()`，混合列表在数据层
  分派（见[混合列表](#混合列表)），或者在渲染前规范化数据。
- 编译后的代码与 Shape 结构绑定；改变 Shape 会改变它的 `id()`，从而改变其缓存文件。
- 编译时会读取当前的 Shape 树，`id()` 也反映调用时刻的树。已编译的渲染器会持续渲染它编译时的那份树，因此在修改已包装为 Shape 的树之后需要调用 `Compile::flush()`；每个进程只构建一次 Shape 即可完全避免此问题。
- Shape 不得包含请求数据——它们是进程级产物。

## 混合列表

一个 Shape 只有一种结构，因此列表项需要不同标记时，在数据层分派：在数据层构建每一项的
标记，把拼好的结果交给 raw Slot。

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

function Blocks(array $blocks): string
{
    $html = '';

    foreach ($blocks as $block) {
        $html .= $block['kind'] === 'link'
            ? sprintf(
                '<a href="%s">%s</a>',
                htmlspecialchars($block['href'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($block['value'], ENT_QUOTES, 'UTF-8')
            )
            : htmlspecialchars($block['value'], ENT_QUOTES, 'UTF-8');
    }

    return $html;
}

$blocks = [
    ['kind' => 'link', 'value' => '文档', 'href' => '/docs'],
    ['kind' => 'text', 'value' => '你好'],
];

$shape = Compile::shape(div(Slot::raw('blocks')));
$shape(['blocks' => Blocks($blocks)]);
```

同构列表用 `Slot::each()`；变体只是单个 item 内部的细节时，可以用预置的布尔键配合
`Slot::if()` 把分派留在模板里。

即时（`render()`）标签树仍然可用于代码片段与调试；参见[基本用法](/zh/guide/basic-usage)。

## 下一步

- [产物与部署](/zh/guide/artifacts) - `pure compile` 产物、`pure check` 与无依赖视图
- [组件](/zh/guide/components) - Component：Shape 的包装与高级用法
- [Props 与 Slot](/zh/guide/props) - Slot 类型与数据绑定参考
