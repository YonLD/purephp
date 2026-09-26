# 组件

**前置**：[Props 与 Slot](/zh/guide/props)；**本页**：用
Component 包装 Shape——调用函数、模板与类型化 props。

Component 是 Shape 的包装与高级用法：一个组件就是一个文件——一个返回
`Pure\Component\Call` 的 PHP 函数，与调用函数相邻的模板（一棵 Shape）与它接受的类型化
props。文件里注册一个惰性工厂，因此 `pure compile` 可以预编译模板，而请求只加载产物。

单元文件是 PHP 源文件：它的 import 与调用函数都放在同一个文件里。下面多数示例假设
应用已在 front controller 中加载 `vendor/autoload.php`；第一个示例自带 autoloader 与
CLI 守卫，因此同一个文件也能作为独立 smoke test 运行，见[快速开始](/zh/guide/getting-started)。

## 第一个组件

```php [components/Card.cmp.php]
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// 组件单元：调用函数 + 模板
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
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

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    echo Card()->title('Title')->content('Content');
}
```

- `register(Card(...))` 从调用函数派生名字与文件、只保存工厂，不构建任何东西；产物较新的请求永远不会调用工厂。
- `prepare()` 是类型化 prop 契约：它的参数就是 props，PHP 强制它们的类型，返回的数组就是
  绑定模板的数据。
- `Card()` 返回 `Call`；props 像标签属性一样链式设置，标记在字符串转换时产出。
- 用 `vendor/bin/pure compile components` 在单元旁生成 `Card.pure.php`（加 `--plain` 还会
  生成 `Card.plain.php`）。`pure compile --list` 会显示
  `Card -> components/Card.cmp.php (component)`；独立 shape 显示为 `file (shape)`，
  带 `#[Template]` 的构建器显示为 `name -> file (template)`。

注册名与单元文件路径可以互换：`component(__DIR__ . '/Card.cmp.php')` 解析到同一个绑定器，
所以组件既能按名调用，也能按文件调用。

## Props

props 就是单元 `prepare()` 钩子的参数：给它们类型和默认值，然后返回给模板的 Slot。永不变化
的值可以直接写死在模板里，每次渲染都可能变化的值放进 bindings。

```php [components/Badge.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\span;

function Badge(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Badge(...),
    factory: static fn () => span(Slot::value('label'))->class(Slot::value('class')),
    prepare: static function (string $label, string $class = 'badge'): array {
        return ['label' => $label, 'class' => $class];
    }
);

Badge()->label('Save')->class('badge');
```

## 链式调用

组件调用可以写得和标签一样：props 用同样的链式 setter 设置，children 直接传给调用，
返回值可以像标签一样嵌套。

```php [components/Card.cmp.php]
<?php

// 同一个单元，改用链式调用
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button, div, h2, li, ul};

function Card(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Card(...),
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
    Card(h2('Pro'))
        ->type('Free')
        ->features([['value' => '10 users'], ['value' => '2 GB']])
        ->text('Sign up for free')
        ->class('btn btn-lg btn-block btn-outline-primary')
);
```

- `component($name, ...$children)` 返回 `Pure\Component\Call`，它实现了
  `Pure\Core\Markup`：`div(Card(...))` 会原样输出并随父树延迟渲染，和标签子节点一致。
- props 绑定 Slot 名，模板用 `Slot::value()`、`Slot::each()`、`Slot::child()` 读取。
  `class()` 与 `style()` 的合并规则与标签 setter 完全相同；`null` 表示不设置该 prop
  （Slot 随后按“未提供”处理，或回退到默认值）。后续传 `null` 不会清除之前保存的值，
  应在设置前决定最终值。通用 `Call` prop 会把 `false` 保存为数据，最终由模板 Slot 决定
  如何渲染；特殊的 `class()` 与 `style()` setter 仍使用标签式的合并规则。
- `Call::props(array $props): self` 可一次设置多个命名 prop。不要直接实例化 `Call`，
  因为它的构造函数是 internal。
链式公共接口的签名如下：

```php
__call(string $prop, array $args): self
class(array|bool|int|float|string|null ...$values): self
style(string|array|null $value): self
props(array $props): self
```

`props()` 遵循 setter 的值规则：`null` 表示不设置该 prop，`Slot` 会被拒绝；需要合并值时
请使用具有特殊行为的 `class()` 或 `style()`。

- children 绑定保留 Slot `children`，模板用 `Slot::raw('children')` 读取。不传 children
  时渲染为空；模板没有 `children` Slot 却传了 children 会抛出异常。
- 模板不读取的 prop 会由开发守卫给出 `did you mean` 提示，`pure check` 也能静态发现。

### 用 prepare() 给 props 加类型

链式调用把 props 当作数据传递，因此类型放在 `prepare` 闭包里而不是调用函数里。它的参数
就是 prop 契约——PHP 会强制类型，缺失或未知的 prop 在渲染前就报错——返回的数组就是绑定
模板的数据：

```php
<?php

function Section(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Section(...),
    factory: static fn () => div(...), // 模板从略
    prepare: static function (string $section, string $class, callable $item): array {
        $data = FeaturesService::section($section);

        return [
            'title' => $data['title'],
            'contents' => array_map(static fn (array $record): string => $item(...$record), $data['items']),
            'class' => $class,
        ];
    }
);

Section()->section('columns')->class('row g-4')->item(IconColumn(...));
```

没有 `prepare` 闭包时，props 直接就是 bindings，适合纯模板组件。`pure check` 会把
`prepare()` 的参数与返回的键同模板 Slot 逐一比对。

### 用 #[Prop] 声明 props 契约

签名表达不了全部信息：prop 与 Slot 名字不一致时，prop 绑定哪个 Slot、列表 prop 的每一项长什么样、
某个 prop 是否准备废弃。`#[Prop]` 注解把这些事实写出来，让 `pure check` 去校验，而不是
靠推断：

```php
<?php

use Pure\Component\Prop;

use function Pure\HTML\div;

register(Card(...),
    factory: static fn () => div(\Pure\Core\Slot::value('title')),
    prepare: static function (
        #[Prop(slot: 'title')] string $text,
        #[Prop(item: 'value')] array $features,
        #[Prop(required: false)] ?string $class = null,
        #[Prop(deprecated: 'use class()')] ?string $style = null,
    ): array {
        return ['title' => $text, 'features' => $features, 'class' => $class, 'style' => $style];
    }
);
```

- `slot` 指定该 prop 绑定的 Slot 名，默认与参数名相同。当 `prepare()` 返回的不是一个可读的
  字面量数组（分步构建或合并而来）时，模板的必填 Slot 改为与声明的 Slot 比对，而不再报告
  “未做比对”的 `info`。
- `item` 指定列表 prop 的每一项在 `Slot::each` 的 item Shape 里填哪个 Slot，检查器会把两者
  对比。
- `required` 声明调用方的义务；与签名矛盾的声明会被报告。
- `deprecated` 携带迁移提示：`pure check` 会在每个绑定该 prop 的调用点打印，开发守卫也会
  在调用处告警。

`#[Trusted]` 标记携带"已渲染好的标记"的 prop：`pure check` 会校验它绑定的是 raw Slot
（markup 绑到文本 Slot 会被转义），开发守卫则会在调用方传入的不是 `Pure\Core\Markup`
时告警——这正是不可信输入流向输出的位置：

下面这段是 `register()` 内部的钩子片段，不是可独立执行的文件：

```php
prepare: static function (#[Trusted] Markup $icon): array
{
    return ['icon' => $icon];
}
```

`#[Binds]` 声明 `prepare()` 返回的键，用于分步构建、或从服务合并 bindings 的场景，让返回
数组无法被读取时，必填 Slot 依然被校验：

动态组装绑定时同样如此：

```php
prepare: #[Binds('title', 'desc')] static function (): array
{
    return PricingService::pricing();
}
```

页面单元的钩子若返回 `...bindings()` 助手的结果，用同样的方式在钩子本身上声明键名：
`prepare: #[Binds('header', 'pricing')] static fn (): array => pricingBindings()`。
当列表 prop 在调用点被绑定为一个数组字面量时，每一项的键会与 Slot 的 item Shape 比对——
`->links([['txet' => '...']])` 会在调用点被报出来。读取多个 Slot 的 item Shape 不需要额外
声明：嵌套 Shape 本身就是契约。

注解由 `pure check` 与开发守卫读取，渲染时完全不会查询；没有注解的单元行为与之前完全一致。

一次组件调用会在直接渲染已编译树的基础上，增加调用对象、prop setter 与
`prepare()` 调用。组件及端到端实测见[规范性能快照](/zh/guide/compiled#性能)与
[基准源码](https://github.com/YonLD/purephp/tree/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench)。

## 组合组件

需要包裹标记的组件从 raw 的 `children` Slot 读取它，调用方则像标签一样把 children
传给调用：

```php [components/Button.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Raw;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\button as htmlButton;

function Button(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Button(...), static fn () =>
    htmlButton(Slot::raw('icon'), Slot::value('label'), Slot::raw('children'))->class('btn')
);

Button(Raw::of('<span>+</span>'))->label('Add');
```

列表同理：在组件的 `prepare()` 或调用点构造子调用（或已渲染字符串）的列表并传给 raw
Slot——它逐元素转成字符串后拼接，所以用不着 `implode()`。若列表项只是普通数据行、不需要逐项
组件逻辑，可以在
模板里直接用 `Slot::each()`。

## 页面

页面就是根标签为文档根（`html`、`svg`、`xml`…）的组件单元。没有单独的页面 API：
用 `register()` 注册、让它的 `prepare()` 钩子提供区块、用 `component()` 渲染，再把调用交给
`renderHTML()` / `renderXML()`，由函数根据文档类型拼接文档声明（`renderHTML()` 是
`<!DOCTYPE html>`，`renderXML()` 是 XML 声明）：

```php [views/features.cmp.php]
<?php

use Pure\Component\Binds;
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{body, head, html, title};
use function Pure\Utils\renderHTML;

function Features(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Features(...),
    factory: static fn () =>
        html(
            head(title(Slot::value('title'))),
            body(Slot::raw('content'))
        ),
    prepare: #[Binds('title', 'content')] static fn (): array => [
        // 页面决定有哪些区块；每个区块取自己的记录。
        'title' => FeaturesService::pageTitle(),
        'content' => FeaturesBody(),
    ]
);

function featuresPage(): string
{
    return renderHTML(component('Features'));
}
```

调用按原样输出（不带文档声明）；`renderHTML()` / `renderXML()` 会补上对应文件头，
所以完整页面 = 该文件头 + 渲染出的片段。

`pure compile --plain` 会把同一个页面写成无依赖视图文件，因此没有安装 purephp 的部署也能
渲染；两种形态下控制器传入同一份 bindings。

## 公共组件边界

应用代码使用命名的公共入口：

- `register(Card(...), $factory)` 注册单元，名字与文件从调用函数派生。
- `component(string $name, mixed ...$children): Call` 为注册名或
  `*.cmp.php` / `*.shape.php` 路径创建调用。
- `Call::props(array $props): self` 一次设置多个命名 prop。

`Pure\Component\Registry` 在类级别标记为 `@internal`。它内部的 binder、缓存和注册
方法可能随实现变化，其签名不是应用兼容性承诺。高级集成若确实需要底层 binder，请把它
隔离在适配器后面，不要让它成为应用契约。

内联树则编译一次并保存 shape：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

function Tag(string $label): string
{
    static $render;
    $render ??= Compile::shape(div(Slot::value('label'))->class('tag'));

    return $render(['label' => $label]);
}
```

组件路径会在内部按名字或路径缓存 binder，因此注册过的单元不需要用户自己写 `static`
变量。

## 缓存

- 单元文件旁存在不早于它的 `*.pure.php` 产物时，直接由产物提供服务；工厂与 Shape 树完全不
  会被触碰。
- 内部组件 binder 会在同一个编译 generation 内按名字或路径缓存；应用代码应使用
  `component()`，不要直接依赖 `Registry`。
- `Compile::cachePath($dir)`——请求加载已生成的 renderer，而不是重新生成。
- `pure compile --check` 让 CI 把过期产物拦下来；长驻 worker 会把已加载的 renderer 留在
  内存里，产物在那里是可选项。

开启 opcache 后，生产环境可以直接加载已编译的产物。实测对照见
[规范性能快照](/zh/guide/compiled#性能)与
[基准源码](https://github.com/YonLD/purephp/tree/c9b33e3adc9c15fbdaa16b749b1cb2c5add6ad16/bench)。

## 即时渲染（片段）

一次性片段可以完全跳过 shape，直接渲染标签树：

```php
<?php

use function Pure\HTML\{div, h2, p};

div(h2('Title'), p('Content'))->class('card')->print();
```

只建议用于片段与调试；生产组件应当编译模板，让转义与结构成本只付一次。

## 可信标记与 CSP

`#[Trusted]` 与 `Pure\Core\Markup` 描述的是信任边界，不会清理输入。请只把应用生成或审核过
的标记传入 raw Slot，校验 URL 协议和事件/style 属性，限制动态标签名；页面包含脚本或其它
活动标记时，配置浏览器 Content Security Policy。预编译的 `*.pure.php` 是可执行 PHP，应从
可信源码构建并限制写入权限。

## Slot 参考

组件是函数；Slot 是模板*内部*的词汇：`Slot::value()`、`Slot::raw()`、`Slot::child()`、
`Slot::each()` 与 `Slot::if()`。完整的数据绑定参考见
[Props 与 Slot](/zh/guide/props)。

## 下一步

- [编译渲染](/zh/guide/compiled)——组件模板如何编译与缓存
- [产物与部署](/zh/guide/artifacts)——产物、缓存与无依赖视图
- [Props 与 Slot](/zh/guide/props)——完整的数据绑定参考
- [事件](/zh/guide/events)——事件属性与浏览器端处理器
