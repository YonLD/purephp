# 产物与部署

**前置**：[编译渲染](/zh/guide/compiled)、[组件](/zh/guide/components)；**本页**：`pure compile` 产物、`pure check` 契约检查、无依赖视图与部署缓存。

磁盘缓存仍会在每个请求中重建 Shape 树。若要在部署时完全不构建 Shape，可以用 `pure` 命令提前编译。

## 预编译产物

```bash
vendor/bin/pure compile src
```

每个 `*.cmp.php` 单元会被编译成相邻的 `*.pure.php` 产物。产物带有 Shape 指纹并返回一个 `Renderer`，因此既不需要 Shape 树，也不需要编译缓存：

```php
<?php

$page = require __DIR__ . '/page.pure.php';

echo $page->render(['title' => 'Users']);
$page->save(__DIR__ . '/out.html', ['title' => 'Users']);
```

::: tip 进阶：独立的 `*.shape.php` 模板
除单元外，`pure compile` 也会发现返回 `Shape` 的 `*.shape.php` 文件——没有调用函数的模板。
组件才是推荐形态；只有当没有组件可以承载该模板时才使用 shape 文件。
:::

- `pure compile <路径>...` 接受文件与目录（递归查找），同时发现 `*.cmp.php` 单元与
  `*.shape.php` 模板，并跳过内容已最新的文件：shape 仍会被加载与编译（因此它引用的任何文件
  发生变化都能被感知），但内容一致的文件会以 `unchanged:` 报告而不是重写。`--list` 不编译，
  直接按 `name -> file (component|page)` 打印发现的单元。`pure compile --check` 不写入任何
  文件，当产物过期或缺失时以退出码 1 结束，适合放在 CI 步骤中。`--plain` 会额外写出下文的
  「无依赖视图」，`--check --plain` 同时校验两种形态。仓库中的示例都是 `*.cmp.php` 单元，
  `vendor/bin/pure compile examples` 可一次编译全部。
- 产物的渲染结果与运行时编译器完全一致（测试按逐字节比对断言），并且读起来就像模板：
  标记仍是标记，动态值写成 `<?= ... ?>`，控制流使用替代语法，闭包只定义一次并导入类的短名。
  HTML 片段承载的是精确的渲染字节，因此不会被重新缩进。产物的 `Renderer::$source`
  为空——文件本身就是源码。
- 动态值通过 `TemplateRuntime` 读取 Slot，编译语义集中在一处：必填 Slot 缺失时抛出
  `MissingSlotException`（必填的值 Slot 与 raw Slot 显式传入 `null` 也会失败，属性 Slot 则保持
  按 `null` 省略），`default:` 提供可选 Slot 的编译期默认值，转义与强制转换与平铺渲染器完全一致；
  仅当 Slot 路径与键名不同时才出现 `path:`。
- 产物还携带根作用域 Slot 清单（`Renderer::$slots`），因此开发守卫无需重建 Shape 树就能报告
  模板从未读取的 binding。

```php
<?php

$pureBody = static function (array $v): string {
    ob_start();
    try { ?><div class="card"><h1><?= TemplateRuntime::text($v, 'title') ?></h1><ul><?php
        foreach (TemplateRuntime::items($v, 'items') as $item1):
            $v2 = TemplateRuntime::scope($item1, 'items[]'); ?><li><?= TemplateRuntime::text($v2, 'label', path: 'items[].label') ?></li><?php
        endforeach; ?></ul></div><?php
    } finally {
        $out = (string)ob_get_clean();
    }

    return $out;
};
```

没有自动的文档声明：树按原样渲染，整份文档的文档声明（HTML 根是
`<!DOCTYPE html>`，XML/SVG 根是 XML 声明）由调用方拼接——标签树或组件调用交给
`renderHTML()` / `renderXML()`，直接渲染 `Renderer` 时用根标签的 `documentHeader()`。
`examples/bootstrap` 的组件都是建立在其上的单元：

```php
<?php

// components/Icon.cmp.php：类型化 prop 契约在 prepare() 钩子里，背后是预编译模板

function Icon(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Icon(...),
    factory: static fn () =>
        svg(
            svgUse()->href(Slot::value('href'))
        )->class(Slot::value('class')),
    prepare: static function (string $href, string $class = 'bi'): array {
        return ['href' => $href, 'class' => $class];
    }
);

// views/features.cmp.php：页面数据由 prepare() 提供
function Features(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Features(...),
    factory: static fn () => html(/* ... */),
    prepare: static fn (): array => [
        'title' => FeaturesService::pageTitle(),
        'content' => FeaturesBody(),
    ]
);

function featuresPage(): string
{
    // renderHTML() 补上文档声明；树本身不带文档声明。
    return renderHTML(component('Features'));
}
```

每个区块都从 service 层（bootstrap 示例中的 `FeaturesService`）取自己的记录，因此页面函数
不携带页面数据，给组件加一个 prop 也永远不需要改页面。

子组件渲染出的字符串直接进入 raw Slot——无需 `(string)` 强制转换——它们组成的列表按顺序拼接。

`component()`（以及它背后的绑定器）会在单元或 shape 文件旁边存在产物、且产物不早于源
文件时直接加载产物，否则调用注册的工厂（每个编译 generation 一次）或编译 shape 文件
（磁盘缓存仍然生效）。它只产出片段——要文档声明就由调用方自己拼接。

bootstrap 示例的 `PlainFeaturesController` 把同一份 bindings 交给示例自带的 `plain()` 助手（一个应用
函数：它 require 视图文件并展开数据）；单一入口
`public/index.php` 为每个页面同时提供两种形态：`/pure/features`、`/pure/pricing` 走页面函数，
`/plain/features`、`/plain/pricing` 走普通视图，开发时可以对照。

- 请使用与生产环境相同的 PHP 次版本号构建产物：指纹与产物头部都嵌入了 PHP 版本（与缓存一致）。
- 产物是构建输出：修改 Shape 后需要重新构建。加载时不会校验 Shape 树，因此请用 `--check`
  发现过期产物。
- 产物还带有它的 `Compile::CACHE_VERSION`：加载由库的其他版本写出的产物时，抛出的是带
  `pure compile` 提示的异常，而不是在产物所描述的 `Renderer` 签名上报错。版本号提升本身就会
  使 `Compile::cachePath()` 里的渲染器失效，却绝不会使模板旁的产物失效，所以 `pure compile`
  是升级流程的一部分。`*.plain.php` 视图只在注释中携带版本号、没有可执行守卫，升级后会
  静默输出过期内容，直到 `pure compile --check --plain` 发现不一致。
- 新鲜度用 `filemtime()` 比较，它的整秒粒度意味着与单元在同一秒写入的产物就已经可用。
  这是有意为之：tar、rsync 或 git checkout 造成的 `touch` 式时间偏移很常见，精确比较会丢弃
  这些产物并逐请求重新编译。而对每个单元做内容哈希实测约每个文件 7 µs，相比之下开启
  opcache 后 require 它的产物约 0.5 µs，所以它也算不上更便宜的守卫。
- 两个会写出同一个产物的源文件（`a.shape.php` 紧邻 `a.cmp.php`）会被 `pure compile` 一并
  拒绝并返回退出码 1，因此 `a.pure.php` 归属哪个模板不会由发现顺序决定。
- 加载 Shape 文件时产生的输出会被丢弃；`pure compile` 只输出构建信息。

## 契约检查

`pure check` 静态校验每个单元的契约，让不匹配在 CI 中失败，而不是等到渲染时：

```bash
vendor/bin/pure check src
```

- 模板读取的 **Slot** 与调用点设置的 **props**：设置了一个模板不读取的 prop 是错误（并给出
  `did you mean` 建议），必填 Slot 没有被绑定也是错误。`prepare()` 钩子把 props 变成绑定：
  它返回字面量数组时，这些键会被解析；返回的是 `...bindings()` 助手的结果、读不出字面量
  时，用 `#[Binds(...)]` 声明键名，运行时计算的绑定则报告为 `info`。
- 组件函数的**参数类型**与 Slot 种类：列表 Slot 需要可迭代值、child 作用域需要数组、值 Slot 需要
  可字符串化值、raw Slot 两者皆可。必填 Slot 对应的可空参数是警告（绑定 `null` 会抛出
  `MissingSlotException`）；既未被函数使用、也不是模板 Slot 的参数同样是警告。
- 没有组件函数的链式单元改查它的 **`prepare()` 闭包**：参数就是 prop 契约，必须与 Slot
  的名字和类型一致；它返回的字面量数组的键必须是模板读取的 Slot（运行时计算出的返回值
  报告为 `info`）。
- **声明注解**用来表达签名说不出的信息。`#[Prop]` 带 `slot`（该 prop 绑定的 Slot 名）、
  `item`（列表 prop 的每一项在 `Slot::each` 的 item Shape 里填的 Slot）、`required`（调用方
  义务）与 `deprecated`（迁移提示）；`#[Trusted]` 标记携带 markup 的 prop，它必须绑定
  raw Slot，且调用方传入的不是 `Pure\Core\Markup` 时开发守卫会告警；`#[Binds]` 用在
  hook 或 `...bindings()` 辅助函数上，在字面量数组读不出来时声明返回的键。声明会与签名和
  模板比对；当 `prepare()` 返回的不是一个可读的字面量数组时，模板的必填 Slot 改为与声明
  比对；调用点绑定了已废弃的 prop 会得到警告。
- 每个被检查文件里的**链式调用**：目标不接受的 `->prop(...)` 是错误（附 `did you mean`
  建议），`->children(...)` 会提示应把 children 传给调用本身，从变量展开的 prop 集合会
  被跳过。绑定为数组字面量的列表 prop 会逐项与 Slot 的 item Shape 比对（缺少必填键、读取了
  Shape 不认识的键都是错误）。目标必须在被检查的文件中，才能得知它接受哪些 props。
- 同一个模板把一个 Slot 同时用作标量（value/raw）与作用域（child/each）是错误；
  `*.shape.php` 模板也会做这项检查。

有错误时退出码为 1，带 `--strict` 时警告也返回 1。`pure check` 不看产物——
产物新鲜度由 `pure compile --check` 负责。

## 组件产物与缓存策略

每个组件都是一个 `*.cmp.php` 单元，因此 `pure compile` 会像其他模板一样为它构建产物。绑定器会比较产物与单元文件的 mtime：产物较新就直接加载（不调用
工厂、不建树、不算指纹），过期或缺失则调用注册的工厂或编译 shape 文件。CI 中用
`pure compile --check` 可以发现过期产物（退出码 1）。

开启哪些取决于部署形态：

- **PHP-FPM**——开启 `Compile::cachePath()` 并构建产物。没有产物时，每个请求都要为该组件
  重建 Shape 树、遍历指纹，然后才渲染：仅 features 页面的骨架编译就要 ~780 µs（冷启动）、
  ~260 µs（磁盘缓存命中，`bench/cache.php`）。产物把这段降为一个 `require`，而在开启
  opcache 时一次 require 远低于 1 µs。
- **长驻 worker**（RoadRunner、Swoole、FrankenPHP）——开启 `Compile::cachePath()` 并保留
  绑定器的路径缓存（`Registry::component()` 内置，内联树用 `static $render`）；renderer 常驻内存，产物可选。
- **`opcache.preload`**——preload 只把代码常驻内存，不会让 static 变量跨请求保留（PHP preload
  RFC 已明确说明），因此不能替代上面两种做法。

开启 opcache 后，一页里每个组件产物的 require 约 0.5µs（22 个产物约 10µs，见
`bench/registry.php`），因此「产物 + opcache」就是生产路径。

## 无依赖导出（可选）

`pure compile --plain` 会在产物旁边额外写出 `*.plain.php`：只有标记与原生 PHP，
渲染时不需要安装 purephp。加载方式就是常规的视图约定——把数据数组展开成局部变量：

```php
<?php

ob_start();
extract($data, EXTR_SKIP);
require 'views/index.plain.php';
$html = (string)ob_get_clean();
```

需要让视图脱离库运行时才用 `--plain`：例如部署只带 `public/` 与 `views/`，
或把模板目录交给其他人。常规数据下无依赖视图逐字节等于产物，并且仅当其根是文档根
（`<html>` 或 XML 树）时前面补上文档声明：页面保留自己的 `<!DOCTYPE html>` / XML 声明，
而片段（`div`、内联 SVG 图标）视图直接以标记开头，因此被 include 时绝不会把文档声明
插进文档中间。而且它是最快的形态：值直接进入 `htmlspecialchars()`，没有运行时访问器调用。
但它是普通视图而非编译组件，严格 Slot 语义仍由产物提供——语义差异与
`@var` 注解说明见[无依赖视图注意事项](#无依赖视图注意事项)。

## 无依赖视图注意事项

无依赖视图是标记 + 原生 PHP——脱离 purephp 也能渲染，但它不携带编译渲染器的
严格 Slot 语义：

- 必填 Slot 缺失是未定义变量，不再抛出 `MissingSlotException`；
- 必填 Slot 显式传入 `null` 时渲染为空，而不是像产物那样（值 Slot 与 raw Slot）失败；
- 文档根的视图会带上 `<!DOCTYPE html>` / XML 声明，而片段视图以标记开头；
- `null` 属性输出为空值，而不是整个属性消失；
- 列表 Slot 不再校验可迭代性，值的字符串化交给 PHP 而不是 `SlotRuntime`；
- raw Slot 与产物一样用 `implode('')` 拼接可字符串化值的可迭代集合；PHP 无法字符串化的元素
  会被强转（警告 + `Array`），而不是像产物那样抛 `InvalidArgumentException`。

顶层 Slot 读取为普通变量，嵌套 Slot 读取为它所在的数组，转义直接内联，因此它和手写
模板一样可移植。

使用组件时，控制器会先渲染组件、再把它们的标记作为 raw bindings 传给页面
Shape，因此视图文件仍然无依赖，而请求处理器会用到库。

视图会按 Shape 结构为每个顶层 Slot 生成 `@var` 注解，静态分析器无需排除规则或额外
配置即可读取这些展开的局部变量：值 Slot 是 `scalar|null|\Stringable`（即
`htmlspecialchars()` 可接受的类型），条件 Slot 是 `mixed`，child 与列表作用域会推导成
array shape 及它们的 iterable；特殊 Slot 名声明在加载器的 `$data` 数组上。这些注解
只是注释，不增加任何输出字节。唯一仍会告警的是没有数组默认值的可选容器 Slot：
生成的读取会回退到 `null`，注解如实反映这一点。

视图是 include，请在生产开启 opcache：关闭时每次渲染都会重新解析文件。

## 下一步

- [编译渲染](/zh/guide/compiled) - Shape 编译、运行时缓存与限制
- [组件](/zh/guide/components) - Component：Shape 的包装与高级用法
- [Compile API](/zh/api/compile) - `Compile`、`Shape`、`Renderer` 类参考
