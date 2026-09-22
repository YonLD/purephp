# Compile API

`Pure\Compile\Compile` 把一棵不含数据的 **Shape** 树编译成扁平的 PHP 渲染器。
静态标记在编译期一次性转义，并作为字面量字符串块输出，因此渲染一个页面只比字符串拼接加上动态值转义略多一点开销。

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, li, p, ul};

// 每个进程构建 + 编译一次
$item  = Compile::shape(li(Slot::value('title')));
$shape = Compile::shape(
    div(
        h1(Slot::value('heading')),
        ul(Slot::each('items', $item))
    )->class('card')
);

// 每个请求用普通数据渲染
echo $shape([
    'heading' => 'Users',
    'items' => [['title' => 'Ada'], ['title' => 'Grace']],
]);
```

对同一棵树，输出与 `Tag::render()` **逐字节一致**，因为两条路径共用同一份转义实现
（`Pure\Core\Escaper`，`@internal`）。

## 类

| 类 | 用途 |
| --- | --- |
| `Pure\Compile\Compile` | 门面：`shape()`、`cachePath()`、`clearCache()`、`flush()`、`guard()` |
| `Pure\Compile\Shape` | 不含数据的树：`__invoke($data)`、`compile()`、`id()`、`print($data)`、`save($path, $data)` |
| `Pure\Compile\Renderer` | 编译后的渲染器：`render($data)`、`save($path, $data, $header = '')`，以及只读属性 `source` / `id` / `slots`（`slots` 是编译时携带的根 Slot 清单） |
| `Pure\Core\Slot` | 占位符构造器（`value`、`raw`、`child`、`each`、`if`）与修饰符 |
| `Pure\Core\MissingSlotException` | 必填 Slot 缺失时抛出，携带完整路径 |

## 组件单元
组件单元把惰性模板工厂注册到调用函数的名字下：调用函数返回 `Call`，用
`component(__FUNCTION__, ...)` 只写一遍组件名；单元的类型化
prop 契约放在 `prepare()` 钩子里：

```php
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
        div(h2(Slot::value('title')), p(Slot::value('content')))->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);
```

| 函数 | 行为 |
| --- | --- |
| `register(Closure $call, ?Closure $factory = null, bool $override = false, ?Closure $prepare = null): void` | 注册组件单元。把调用函数传进来——`register(Card(...), $factory)`——名字与文件从它派生。工厂必须惰性，可返回标签树或 `Shape`；`$prepare` 是链式调用可选的类型化 prop 契约（props→bindings 钩子） |
| `component(string $name, mixed ...$children): Call` | 开始一次链式调用：props 像标签属性一样设置，返回值是 `Markup`，可像标签一样嵌套；`$name` 是已注册的名字或模板路径，单元自己的调用函数传 `__FUNCTION__` |

链式调用按原样产出树，**不带文档头**；整份文档的文档头由调用方处理：
标签树或组件调用交给 `Pure\Utils\renderHTML()` / `renderXML()`，或者自己拼接
（`$root->documentHeader()`，或常量 `HTML::DOCUMENT_HEADER` /
`XML::DOCUMENT_HEADER`）。

需要自己持有或传递绑定器时，用 `Registry::component($source)`，它返回
`Closure(array $data): string`，接受字符串键的数据数组。

链式调用绑定 props：`Card($children)->title($title)` 每个 prop 对应一个 Slot，`null`
表示不设置该 prop，children 绑定保留 Slot `children`（模板用 `Slot::raw('children')`）。
`Call` 与 `Raw` 都实现 `Pure\Core\Markup`：Markup 子节点原样输出、随树延迟渲染，其他
子节点则冻结为文本并转义。组件调用不能出现在数据无关的 Shape 树里——请把它的 markup 放进
raw Slot。

传入字符串时视为注册名、`*.cmp.php` 单元路径或 `*.shape.php` 模板
路径。同名注册到另一个文件、或同一文件注册另一个名字都会抛异常，除非传 `override: true`；
一个单元文件只注册一个组件。名字与它单元文件的路径解析到同一个绑定器。

对文件而言，相邻的 `*.pure.php` 产物存在且不早于单元/shape 文件时直接加载，生产环境因此
跳过工厂调用与 Shape 树构建；否则调用工厂（每个编译 generation 一次）或编译 shape 文件
（磁盘缓存仍然生效）。文件缺失、模板未返回标签树或 `Shape`、产物未返回 `Renderer` 都会抛出带文件名
的 `RuntimeException`。用 `pure compile` 为所有 `*.shape.php` 与 `*.cmp.php` 构建产物，
用 `pure compile --list` 打印发现的单元，用 `pure compile --check` 在 CI 中保证产物新鲜，
用 `pure check` 在不写入任何文件的前提下校验组件契约（Slot vs. 绑定与参数类型）。

## Shape 与数据

Shape 就是普通标签树，只是把动态值替换为 `Slot` 占位符。Shape 里不能包含请求数据，并且必须
**每进程只构建一次**——文件形式由 `Registry::component()` 的按名或按路径缓存保证，内联树
放进调用函数内的 `static` 变量中，绝不能放在请求处理器里。
标准 PHP-FPM 下 `static` 每个请求都会重置，因此请启用 `Compile::cachePath()`，让请求加载
已编译的渲染器而不是重新生成。

子组件的标记就是普通字符串，因此要经 raw Slot 进入模板——直接作为字符串子节点会被转义成文本：

```php
<?php

$shape = Compile::shape(div(Slot::raw('header'), Slot::each('rows', $row))->class('page'));
$shape(['header' => Header(), 'rows' => $rows]);
```

## Slot 类型

| 构造器 | 值 | 行为 |
| --- | --- | --- |
| `Slot::value($name)` | 可字符串化或 `null` | 位置决定语义：子节点位转字符串后转义（`null` 渲染为空内容，`true` 为 "1"）；属性位遵循 `setAttr()`（`true` 渲染 `name="name"`，`false`/`null` 省略该属性） |
| `Slot::raw($name)` | 可字符串化值、`null`，或这类值的可迭代集合 | 原样输出，绝不转义；集合按顺序拼接 |
| `Slot::child($name, $shape)` | 数组 | 作为 `$shape` 的嵌套数据作用域 |
| `Slot::each($name, $shape)` | 数组的可迭代集合 | 为每个 item 渲染一次 `$shape` |
| `Slot::if($name, $then, $else = null)` | 真值判断 | `$data[$name]` 为真值时渲染 `$then`，否则渲染 `$else`；键缺失视为 false，绝不抛异常 |

修饰符：

- `->required(false)`——允许 Slot 缺失。
- `->default($value)`——键缺失时使用的回退值。
- `Slot::if()` 会以 `LogicException` 拒绝这两个修饰符。
- 嵌套作用域直接读取 `$data[$name]`，数据 Shape 由调用方在渲染前准备好。

值 Slot 与 raw Slot 必须可字符串化：接受 `null`、标量和 `Stringable`（含 `Raw`）；其它对象
抛出 `InvalidArgumentException`，错误信息中会指出完整 Slot 路径。
`raw` Slot 额外接受这类值的可迭代集合并原样拼接；某个元素不可字符串化时，报错会带上下标，
例如 `slot 'items[2]' must be stringable`。

选择列表 Slot 看标记是否已渲染：`raw()` 直接拼接已渲染好的标记（传渲染好的字符串、单个
`Raw` 或它们的列表），`each()` 则是数据驱动、逐项用自己的 shape 渲染。

## 静态子树折叠

不含任何 Slot 的子树就是静态标记。编译器在编译期把它渲染一次并折叠成单个字面量，
因此这类子树在渲染期没有任何开销：

```php
<?php

$shape = Compile::shape(div(
    Slot::raw('header'),
    div('Static footer')->class('footer'),
    Slot::value('title')
));
```

`div('Static footer')` 会被折叠成字面量；`header` 与 `title` 保持动态，已渲染好的
`Header()` 标记则在渲染时经 raw Slot 进入。

## 结构指纹

`Shape::id()` 是 Shape 结构的 sha1 指纹：标签名、属性名与属性值、Slot 种类与名称、默认值、
嵌套 Shape 以及库缓存版本。它无需编译即可计算，并被用作磁盘渲染器缓存的键：生成的源码以它为
键存放，因此结构任何一处不同的两个 Shape 不可能共用同一个缓存的渲染器。`*.pure.php` 产物把指纹
记在头部注释里，而 `pure compile --check` 是通过把产物与现场生成的源码逐字节比对来识别过期的。
它并不是 `component()`
解析组件所依据的东西——那是注册名或单元文件——而且只有*数据*变化时它不会改变。真正用得上它
的是那种为每个变体组装不同 Shape 的应用：指纹就是存放它们的记忆表的一个廉价而稳定的键：

```php
<?php

$shapes[$classList . '|' . $item->id()] ??= Compile::shape(...); // 模板从略
```

## 编译产物 API

```php
<?php

$compiled = $shape->compile();

$compiled->render($data);                    // 返回 string
$compiled->save($path, $data);               // 写入文件，返回写入的字节数
$compiled->save($path, $data, $header);      // 在文件开头补上 $header
$compiled->source;                           // 生成的 PHP 源码（预编译产物为空）
$compiled->id;                               // 结构指纹（与 Shape::id() 相同）
```

`Shape::save($path, $data)` 是面向用户的便捷方法：写出渲染结果，并补上根标签的文档声明
（例如 `<!DOCTYPE html>` 或 XML 声明），除非你传入自己的声明。
`Renderer::save()` 则把声明作为可选的第三个参数，默认为空，因此需要整份文档的处理器
自己拼接：`HTML::DOCUMENT_HEADER . $renderer->render($data)`。

## 磁盘缓存

默认关闭。在引导阶段启用一次：

```php
<?php

use Pure\Compile\Compile;

Compile::cachePath(__DIR__ . '/var/cache/purephp');
```

- 缓存文件以 `Shape::id()` 命名，原子写入（临时文件 + rename），内容是返回编译闭包的
  纯 PHP，因此 opcache 可以直接提供它们。
- 缓存条目的头部与预期的 id、缓存版本或 PHP 版本不匹配时，该条目会被丢弃并重新生成。
- `Compile::clearCache()` 删除由本库写入的缓存文件。
- 生成的源码也会按指纹在内存中记忆化，因此在同一进程内重建同一棵树会重新求值缓存的
  源码，而不是重新生成。这份记忆有
  字节预算上限（超限时先丢弃最旧的源码，比预算还大的单个源码不会被保留），因此随请求
  变化的结构不会让它无限增长。可通过环境变量 `PURE_COMPILE_MEMO_BYTES` 调整预算
  （设为 `0` 即关闭记忆化）。
- `Compile::flush()` 让内存中的渲染器失效（每个 Shape 在下次使用时重新编译）；它不会删除
  缓存文件。

缓存目录必须是私有目录：归 PHP 运行用户所有、组与其他用户不可写（`cachePath()` 会以
0700 创建缺失目录，并拒绝权限过松或属主不符的目录），且应位于 Web 根目录之外。不要把
缓存目录直接指向 `/tmp` 这类共享位置。只有在想强制重新生成时才在部署之间删除它。

## 每请求守卫

每请求编译 Shape 比渲染已编译的渲染器更慢。启用开发守卫来检测这种情况，同时暴露输出中
看不出来的问题：

```php
<?php

Compile::guard(true); // 或设置 PURE_COMPILE_GUARD=1
```

- 当同一调用点在单个进程内第 20 次调用 `Compile::shape()` 时，会触发
  `E_USER_WARNING`，建议改用 `static $shape ??=` 模式。
- 模板从未读取的数据键会被报告，并给出 `did you mean` 建议，因此拼错的 binding 会
  显式失败，而不是像值不存在一样照常渲染。
- 与标准属性名只差一个字符的属性方法（`->clas(...)`、`->hreff(...)`）会发出警告，
  而不是静默变成自定义属性；确实需要自定义属性的调用可以忽略它。

每条警告在单个进程内每个对象只触发一次。关闭守卫（默认）时，这些检查只花一次属性读取。

## 错误

- 必填 Slot 缺失：`Pure\Core\MissingSlotException`，带完整路径，例如
  `slot 'items[].title' is required but was not provided.`。当作用域中还有其他键时，
  信息会建议最接近的键名（拼写错误）或把它们列出。必填的值 Slot 与 raw Slot 显式传入 `null`
  时抛出 `slot 'items[].title' is required but was null.`；通过 `component()` 调用渲染时，
  信息会加上组件名或模板路径前缀（`component 'Card': slot 'title' is required ...`）。
- 位置错误（raw Slot 用作属性值）或缺少 Shape：编译期抛 `LogicException`。
- 列表不可迭代、item 或作用域不是数组、值不可字符串化：渲染期抛
  `InvalidArgumentException`（对 `Slot::if()` 使用 `required()` / `default()` 会抛
  `LogicException`）。

## 含 Slot 的树不能使用其它输出路径

含 Slot 的树调用 `render()`、`print()` 和 `save()` 会抛出 `LogicException`，因为
没有可绑定的数据。`toJSON()` 把 Slot 描述为 `['slot' => 'name']`。

## 性能

`bench/compare.php` 量的是 604 元素页面上的各条路径，`examples/bootstrap/bench.php` 量的则是
一个由组件函数组合起来的真实页面；实测行见 `bench/README.md`，[编译渲染指南](/zh/guide/compiled#性能)
说明各项开销分别在什么时候占主导。绝对数值会随 PHP 版本、opcache 与 CPU 变化，因此对照之前
请先自己跑一遍：

```bash
php bench/compare.php
php examples/bootstrap/bench.php
```

## 限制

- 标签名不能依赖数据：一个 Shape 始终使用相同的标签。结构变化请用 `Slot::if()`，
  或者在渲染前规整数据。
- Shape 只在 PHP 进程的生命周期内存在。长驻 worker（或 `opcache.preload`）下是每个
  worker 一次；标准 PHP-FPM 下 Shape 树会在每个请求中重建、渲染器会被重新生成，反而比
  `render()` 更慢。请启用 `cachePath()`，让请求加载生成的渲染器而不是重新生成。
- 编译渲染拿编译成本换速度：为每进程只渲染一次的 Shape 做编译比 `render()` 更慢。请编译
  会被反复渲染的页面和组件。
