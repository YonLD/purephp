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
| `Pure\Compile\Compile` | 门面：`shape(Tag $shape): Shape`、`cachePath(?string $dir): void`、`clearCache(): int`、`flush(): void`、`guard(bool $enabled = true): void` |
| `Pure\Compile\Shape` | 不含数据的树：`__invoke(array $data): string`、`compile(): Renderer`、`id(): string`、`print(array $data): void`、`save(string $path, array $data, ?string $header = null): int|false` |
| `Pure\Compile\Renderer` | 编译后的渲染器：`render(array $data): string`、`save(string $path, array $data, string $header = ''): int|false`，以及只读属性 `source`、`id`、`slots` |
| `Pure\Core\Slot` | 占位符构造器（`value`、`raw`、`child`、`each`、`if`）与修饰符 |
| `Pure\Compile\Template` | 标记模板构建器，供 `pure compile --list` 与 `pure check` 使用 |
| `Pure\Core\MissingSlotException` | 必填 Slot 缺失时抛出，携带完整路径 |

## 组件单元
组件单元把惰性模板工厂注册到调用函数的名字下：调用函数返回 `Call`，用
`component(__FUNCTION__)`（只有模板读取 `children` Slot 时才写
`component(__FUNCTION__, ...$children)`）只写一遍组件名；单元的类型化
prop 契约放在 `prepare()` 钩子里：

```php
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
        div(h2(Slot::value('title')), p(Slot::value('content')))->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);

echo Card()->title('Title')->content('Content');
```

| 函数或类型 | 行为 |
| --- | --- |
| `register(Closure $call, ?Closure $factory = null, bool $override = false, ?Closure $prepare = null): void` | 注册组件单元。把调用函数传进来——`register(Card(...), $factory)`——名字与文件从它派生。工厂必须惰性，可返回标签树或 `Shape`；`$prepare` 是链式调用可选的类型化 prop 契约（props→bindings 钩子） |
| `component(string $name, mixed ...$children): Call` | 开始一次链式调用：props 像标签属性一样设置，返回值是 `Markup`，可像标签一样嵌套；`$name` 是已注册的名字或模板路径，单元自己的调用函数传 `__FUNCTION__` |
| `Call::props(array $props): self` | 一次设置多个命名 prop；值遵循链式 setter 的规则。`Call` 应通过 `component()` 创建，不要直接调用其内部构造函数。 |

链式调用按原样产出树，**不带文档头**；整份文档的文档头由调用方处理：
标签树或组件调用交给 `Pure\Utils\renderHTML()` / `renderXML()`，或者自己拼接
（`$root->documentHeader()`，或常量 `HTML::DOCUMENT_HEADER` /
`XML::DOCUMENT_HEADER`）。

链式调用绑定 props：`Card()->title($title)` 每个 prop 对应一个 Slot，`null`
表示不设置该 prop，children 绑定保留 Slot `children`（模板用 `Slot::raw('children')`），
只有读取该 Slot 的单元才在调用函数里声明 children。
`Call` 还提供 `props(array $props): self`，可一次设置一组命名 prop。后续传 `null` 不会清除
之前已经保存的值，应在设置前决定最终值。通用组件 prop 会把 `false` 保存为数据，最终由
模板 Slot 决定如何渲染；特殊的 `class()` 与 `style()` setter 仍使用标签式的合并规则。

`Call` 与 `Raw` 都实现 `Pure\Core\Markup`：Markup 子节点原样输出、随树延迟渲染，其他
子节点则冻结为文本并转义。组件调用不能出现在数据无关的 Shape 树里——请把它渲染出的标记放进
raw Slot。

应用代码的公共入口是 `register()` 与 `component()`。`Pure\Component\Registry` 明确标记为
`@internal`；它内部的 binder 与缓存方法可能随实现变化，其签名不是应用兼容性承诺。`component()`
接受注册名、`*.cmp.php` 单元路径或 `*.shape.php` 模板路径。同名注册到另一个文件、或同一
文件注册另一个名字都会抛异常，除非传 `override: true`；一个单元文件只注册一个组件。名字
与它单元文件的路径解析到同一个内部绑定器。

对文件而言，相邻的 `*.pure.php` 产物存在且不早于单元/shape 文件时直接加载，生产环境因此
跳过工厂调用与 Shape 树构建；否则调用工厂（每个编译 generation 一次）或编译 shape 文件
（磁盘缓存仍然生效）。文件缺失、模板未返回标签树或 `Shape`、产物未返回 `Renderer` 都会抛出带文件名
的 `RuntimeException`。用 `pure compile` 为所有 `*.shape.php` 与 `*.cmp.php` 构建产物。`pure compile --list`
会打印实际发现的类型：`name -> file (component)`、`file (shape)`，以及带 `#[Template]`
的 `name -> file (template)`；它不会编译。`pure compile --check` 用于在 CI 中保持产物新鲜。
`pure check` 校验组件契约（Slot vs. 绑定与参数类型），不写产物；但它会先 `require` 每个单元，
再执行其 factory（如果存在）来构建 Shape。因此只对可信源码运行，并放在隔离的 CI 进程中。

## Shape 与数据

Shape 就是普通标签树，只是把动态值替换为 `Slot` 占位符。Shape 里不能包含请求数据，并且应当
**每进程只构建一次**——文件形式由内部的按路径 binder 缓存复用，内联树则放进调用函数内的
`static` 变量中，绝不能放在请求处理器里。标准 PHP-FPM 下 `static` 每个请求都会重置，因此请启用
`Compile::cachePath()`，让请求加载已编译的渲染器而不是重新生成。

子组件的标记就是普通字符串，因此要经 raw Slot 进入模板——直接作为字符串子节点会被转义成文本：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, li};

$row = Compile::shape(li(Slot::value('title')));
$shape = Compile::shape(
    div(Slot::raw('header'), Slot::each('rows', $row))->class('page')
);
$shape([
    'header' => '<header>用户</header>',
    'rows' => [['title' => 'Ada']],
]);
```

## Slot 类型

Slot 构造器、值类型、位置相关语义与错误路径的唯一规范表在
[Props 与 Slot](/zh/guide/props#slot-参考)：它覆盖构造参数、位置相关的 value/raw 语义、
修饰符与带路径的错误，本页不重复该表。

使用 API 时请留意这两条边界：

- `required(false)` 与 `default($value)` 会使值/raw Slot 可选，但必填值/raw Slot 在子节点/文本位
  仍拒绝显式 `null`。属性位的 `Slot::value()` 遵循 `setAttr()`，会省略 `null`；raw 不能
  用作属性值。对 `child` 与 `each`，回退值仍必须分别是数组与可迭代值；`default(null)`
  不会把容器变成空容器。静态分析 docblock 仍可能为可选容器标出 `|null`，但运行时 accessor
  仍会执行类型检查。
- `Slot::value()` 会按位置转义文本与普通属性；`Slot::raw()` 会原样输出。转义不等于 URL
  校验，也不能替代 Content Security Policy。

## 静态子树折叠

不含任何 Slot 的子树就是静态标记。编译器在编译期把它渲染一次并折叠成单个字面量，
因此这类子树在渲染期没有任何开销：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

$shape = Compile::shape(div(
    Slot::raw('header'),
    div('Static footer')->class('footer'),
    Slot::value('title')
));
```

`div('Static footer')` 会被折叠成字面量；`header` 与 `title` 保持动态，已渲染好的
`Header()` 标记则在渲染时经 raw Slot 进入。

## 结构指纹

`Shape::id()` 是 Shape 结构的 SHA-1 指纹：标签名、属性名与属性值、Slot 种类与名称、默认值、
嵌套 Shape、库缓存版本，以及运行时的 `PHP_MAJOR_VERSION.PHP_MINOR_VERSION`。它无需编译即可
计算，并被用作磁盘渲染器缓存的键：生成的源码以它为键存放，因此结构或 PHP 次版本不同的两个
Shape 不可能共用同一个缓存的渲染器。`*.pure.php` 产物把指纹记在头部注释里，而
`pure compile --check` 通过把产物与现场生成的源码逐字节比对来识别过期的。它不是公共
`component()` 解析组件的依据——那是注册名或单元文件——而且只有*数据*变化时它不会改变。
由于 PHP 次版本参与盐值，构建产物与缓存时应使用生产环境的相同 PHP 次版本，不要跨次版本复制。
真正用得上它的是为每个变体组装不同 Shape 的应用：在同一 PHP 次版本内，指纹是存放它们的
廉价而确定的键：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

$classList = 'card';
$shapes = [];
$variant = Compile::shape(div(Slot::value('label')));
$shapes[$classList . '|' . $variant->id()] ??= $variant;
```

## 编译产物 API

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

$shape = Compile::shape(div(Slot::value('title')));
$data = ['title' => '用户'];
$path = 'users.html';
$header = '';
$compiled = $shape->compile();

$compiled->render($data);                    // 返回 string
$compiled->save($path, $data);               // 写入文件，返回写入的字节数
$compiled->save($path, $data, $header);      // 在文件开头补上 $header
$compiled->source;                           // 生成的 PHP 源码（预编译产物为空）
$compiled->id;                               // 结构指纹（与 Shape::id() 相同）
$compiled->slots;
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

`Compile::cachePath()` 会以 `0700` 创建缺失目录，并拒绝组或其他用户可写的权限；当 POSIX
属主 API 可用时，也会拒绝属主不符的目录。该方法不会检查路径是否位于 Web 根目录之外，因此
部署仍应把目录放在文档根目录之外，并使用专用 `0700`（或更严格）目录。不要把缓存目录直接
指向 `/tmp` 这类共享位置。只有在想强制重新生成时才在部署之间删除它。

## 每请求守卫

每请求编译 Shape 比渲染已编译的渲染器更慢。启用开发守卫来检测这种情况，同时暴露输出中
看不出来的问题：

```php
<?php

use Pure\Compile\Compile;

Compile::guard(true); // 或 PURE_COMPILE_GUARD=1
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
  `LogicException`）。列表项不是数组时，信息会列出条目 Shape 读取的键，或说明它不读取任何
  键：`slot 'items[]' must be an array, string given. The item shape of this slot reads
  'a' and 'b', so each item must be an array.`

## 含 Slot 的树不能使用其它输出路径

含 Slot 的树调用 `render()`、`print()` 和 `save()` 会抛出 `LogicException`，因为
没有可绑定的数据。`toJSON()` 把 Slot 描述为 `['slot' => 'name']`。

## 性能

[规范性能快照](/zh/guide/compiled#性能)是渲染器、产物、缓存与页面级开销的唯一实测参考，
并注明对应基准 commit、机器与记录日期。绝对结果会随 PHP 版本、opcache 与 CPU 变化，因此
对照前应使用[基准源码](https://github.com/YonLD/purephp/tree/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench)
复现相关路径：

```bash
php bench/compare.php
php examples/bootstrap/bench.php
```

## 可信标记与产物

`Slot::raw()`、`Raw::of()` 以及组件的 `Markup` 子节点都会绕过输出转义。它们是信任边界，
不是清理器：只传入由应用生成或审核过的标记，校验 URL 协议与事件/style 属性，并把动态标签名
限制在白名单内。页面有意包含脚本、样式或其它活动内容时，仍必须配置浏览器 Content Security
Policy。

`*.pure.php` 是由可信源码树生成的可执行 PHP。请在 CI 中构建并验证产物，限制写入权限，不要
把它作为公开静态文件提供，也不要接受不可信用户上传的产物。产物的转义契约不能使不可信源码或
不可信的 `Raw` 标记变安全。

## 限制

- 标签名不能依赖数据：一个 Shape 始终使用相同的标签。结构变化请用 `Slot::if()`，
  或者在渲染前规整数据。
- Shape 只在 PHP 进程的生命周期内存在。长驻 worker 可以在请求之间复用它们；
  `opcache.preload` 不会在请求之间保留它们的 static 状态。标准 PHP-FPM 下 Shape 树会在每个
  请求中重建、渲染器会被重新生成，反而比 `render()` 更慢。请启用 `cachePath()`，让请求加载
  生成的渲染器而不是重新生成。
- 编译渲染拿编译成本换速度：为每进程只渲染一次的 Shape 做编译比 `render()` 更慢。请编译
  会被反复渲染的页面和组件。
