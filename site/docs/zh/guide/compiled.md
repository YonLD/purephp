# 编译渲染

**前置**：[组件](/zh/guide/components)；**本页**：组件模板的编译、运行时缓存与性能。

编译渲染把不含数据的 **Shape**——组件的模板——转换为扁平的 PHP 渲染器。静态标记在编译期只转义一次，并作为字面量字符串输出，因此渲染页面的开销仅比字符串拼接加上动态值转义略高——与编译型模板引擎持平。

组件的工厂返回一棵带 `Slot` 占位符的裸标签树；注册表会把它包装成 `Shape`——对内联树，
`Compile::shape()` 做的正是同一个包装。模板**每个进程只构建一次**——长驻 worker、预加载或
CLI 进程，或任何在请求之间保留 PHP 状态的运行时。标准 PHP-FPM 下每个请求都是全新的，因此请启用磁盘缓存（见[缓存](#缓存)），
或用预编译产物代替运行时编译（见[产物与部署](/zh/guide/artifacts)）。

## Shape、Slot、Renderer

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, li, ul};

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

function Card(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
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
```

模板内部：嵌套 Shape 用 `Slot::child()`，列表用 `Slot::each()`，可选/条件标记用
`Slot::if()`，已渲染的子组件经 `Slot::raw()` 注入。

### 列表

```php
<?php

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
- 缓存文件以 `Shape::id()` 为内容寻址；Shape 变化会生成新文件。
- 写入是原子的（临时文件 + 重命名），因此并发 worker 是安全的。
- 缓存文件是普通 PHP，对 opcache 友好。目录必须是私有目录：归 PHP 运行用户所有、
  组与其他用户不可写（缺失时以 0700 创建）、并位于 Web 根目录之外——`cachePath()`
  会拒绝权限过松或属主不符的目录；不要把缓存目录直接指向 `/tmp` 这类共享位置。
- `Compile::clearCache()` 会删除由本库写入的文件。
- `Compile::flush()` 会使内存中的渲染器失效（在部署后的长驻 worker 中很有用）。

要发现每个请求都重新构建（而不是被记忆化）的 Shape，并让输出中看不出来的问题被报告出来，请启用开发守卫：

```php
<?php

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

每个请求有两项开销：进程拿到一个渲染器要付的代价，以及用它渲染要付的代价。实测行见
`bench/README.md`；绝对数值会随 PHP 版本、opcache 与 CPU 变化，所以先在自己的机器上跑一遍
再与下表对照（PHP 8.1.34，单个 604 元素、200 行的页面，`php bench/compare.php`）。

| 路径 | 每次渲染耗时 | 端到端加速 |
| --- | --- | --- |
| 构建树 + `render()` | ~1.2 ms | 1× |
| 仅渲染（复用同一棵树） | ~345 µs | 3.4× |
| 编译 Shape + 数据 | ~180 µs | 6.6× |
| 编译静态树（字面量） | < 1 µs | — |

开启 opcache 后，构建树依然昂贵，而编译路径几乎不变，因此加速比落在 5.5×，开启 JIT 时为
4.6×。预编译产物路径把构建从第二列里彻底移除：对单个页面 Shape，构建加编译约 2.9 ms，而
require 它的产物只需 ~25–67 µs（`php bench/artifact.php --write && php bench/artifact.php`）。

一整个页面的开销取决于它如何组合。bootstrap 的 features 页面用组件函数拼正文，因此
`examples/bootstrap/bench.php` 量的是真实页面而不是单个 Shape：标签树 ~340 µs/op，页面函数
跑在它的产物之上 ~104 µs/op（2.8–3.3×），普通视图 ~20 µs/op。该基准的
`skeleton artifact + bindings` 行渲染的是页面*模板*，组件标记已经绑定好了，所以它的
~1.5 µs 是每个 Shape 的数值，而不是一次页面渲染。

## 限制

- 标签名不能依赖数据：Shape 始终使用相同的标签。结构变化请使用 `Slot::if()`，混合列表在数据层
  分派（见[混合列表](#混合列表)），或者在渲染前规范化数据。
- 编译后的代码与 Shape 结构绑定；改变 Shape 会改变它的 `id()`，从而改变其缓存文件。
- 编译时会读取当前的 Shape 树，`id()` 也反映调用时刻的树。已编译的渲染器会持续渲染它编译时的那份树，因此在修改已包装为 Shape 的树之后需要调用 `Compile::flush()`；每个进程只构建一次 Shape 即可完全避免此问题。
- Shape 不得包含请求数据——它们是进程级产物。

## 混合列表

一个 Shape 只有一种结构，因此列表项需要不同标记时，在数据层分派：逐项调用合适的组件函数，
把拼好的标记交给 raw Slot。

```php
<?php

function Blocks(array $blocks): string
{
    $html = '';

    foreach ($blocks as $block) {
        $html .= $block['kind'] === 'link'
            ? LinkBlock($block['value'], $block['href'])
            : TextBlock($block['value']);
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
