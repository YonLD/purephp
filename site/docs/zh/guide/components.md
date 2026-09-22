# 组件

**前置**：[Props 与 Slot](/zh/guide/props)；**本页**：用
Component 包装 Shape——调用函数、模板与类型化 props。

Component 是 Shape 的包装与高级用法：一个组件就是一个文件——一个返回
`Pure\Component\Call` 的 PHP 函数，紧挨着它渲染的模板（一棵 Shape）与它接受的类型化
props。文件里注册一个惰性工厂，因此 `pure compile` 可以预编译模板，而请求只加载产物。

## 第一个组件

```php [components/Card.cmp.php]
<?php

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

echo Card()->title('Title')->content('Content');
```

- `register(Card(...))` 从调用函数派生名字与文件、只保存工厂，不构建任何东西；产物较新的请求永远不会调用工厂。
- `prepare()` 是类型化 prop 契约：它的参数就是 props，PHP 强制它们的类型，返回的数组就是
  绑定模板的数据。
- `Card()` 返回 `Call`；props 像标签属性一样链式设置，标记在字符串转换时产出。
- 用 `vendor/bin/pure compile components` 在单元旁生成 `Card.pure.php`（加 `--plain` 还会
  生成 `Card.plain.php`）。`pure compile --list` 会打印发现的所有单元。

注册名与单元文件路径可以互换：`component(__DIR__ . '/Card.cmp.php')` 解析到同一个绑定器，
所以组件既能按名调用，也能按文件调用。

## Props

props 就是单元 `prepare()` 钩子的参数：给它们类型和默认值，然后返回进模板的 Slot。永不变化
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
  （Slot 随后按“未提供”处理，或回退到默认值）。
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

签名表达不了全部信息：prop 与 Slot 名字不一致时它绑定谁、列表 prop 的每一项长什么样、
某个 prop 是否准备废弃。`#[Prop]` 注解把这些事实写出来，让 `pure check` 去校验，而不是
靠推断：

```php
<?php

use Pure\Component\Prop;

register(Card(...),
    factory: static fn () => div(...), // 模板从略
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
  字面量数组（分步构建或合并而来）时，模板的必填 Slot 改为与声明的 Slot 比对，而不再是一句
  “未做比对”的 `info`。
- `item` 指定列表 prop 的每一项在 `Slot::each` 的 item Shape 里填哪个 Slot，检查器会把两者
  对比。
- `required` 声明调用方的义务；与签名矛盾的声明会被报告。
- `deprecated` 携带迁移提示：`pure check` 会在每个绑定该 prop 的调用点打印，开发守卫也会
  在调用处告警。

`#[Trusted]` 标记携带"已渲染好的 markup"的 prop：`pure check` 会校验它绑定的是 raw Slot
（markup 绑到文本 Slot 会被转义），开发守卫则会在调用方传入的不是 `Pure\Core\Markup`
时告警——这正是不可信输入流向输出的位置：

```php
prepare: static function (#[Trusted] Markup $icon): array
{
    return ['icon' => $icon];
}
```

`#[Binds]` 声明 `prepare()` 返回的键，用于分步构建、或从服务合并 bindings 的场景，让返回
数组读不出来时必填 Slot 依然被校验：

```php
prepare: #[Binds('title', 'desc')] static function (): array
{
    return PricingService::pricing();
}
```

页面单元的钩子若返回 `...bindings()` 助手的结果，用同样的方式在钩子本身上声明键名：
`prepare: #[Binds('header', 'pricing')] static fn (): array => pricingBindings()`。
当列表 prop 在调用点被绑定为一个数组字面量时，每一项的键会与 Slot 的 item Shape 比对——
`->links([['txet' => '...']])` 会在写下的地方被报出来。读取多个 Slot 的 item Shape 不需要额外
声明：嵌套 Shape 本身就是契约。

注解由 `pure check` 与开发守卫读取，渲染时完全不会查询；没有注解的单元行为与之前完全一致。

一次组件调用比直接渲染已编译的树多花约 2 微秒：调用对象、prop setter 与 `prepare()` 调用各占
一部分。产物与无依赖视图路径不受影响，`examples/bootstrap/bench.php` 会分别报告两条路径。

## 组合组件

需要包裹 markup 的组件从 raw 的 `children` Slot 读取它，调用方则像标签一样把 children
传给调用：

```php [components/Button.cmp.php]
<?php

function Button(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Button(...), static fn () =>
    button(Slot::raw('icon'), Slot::value('label'), Slot::raw('children'))->class('btn')
);

Button(Icon()->href('#plus'))->label('Add');
```

列表同理：在组件的 `prepare()` 或调用点构造子调用（或已渲染字符串）的列表并传给 raw
Slot——它逐元素转成字符串后拼接，所以用不着 `implode()`。若列表项只是普通数据行、不需要逐项
组件逻辑，可以在
模板里直接用 `Slot::each()`。

## 页面

页面就是根标签为文档根（`html`、`svg`、`xml`…）的组件单元。没有单独的页面 API：
用 `register()` 注册、让它的 `prepare()` 钩子提供区块、用 `component()` 渲染，再把调用交给
`renderHTML()` / `renderXML()`，由函数按名称拼接文档声明（`renderHTML()` 是
`<!DOCTYPE html>`，`renderXML()` 是 XML 声明）：

```php [views/features.cmp.php]
<?php

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
        // 页面决定有哪些区块，每个区块自己取数据。
        'title' => FeaturesService::pageTitle(),
        'content' => FeaturesBody(),
    ]
);

function featuresPage(): string
{
    // 引擎按原样输出树；renderHTML() 负责拼接文档声明。
    return renderHTML(component('Features'));
}
```

调用按原样输出（不带文档声明）；`renderHTML()` / `renderXML()` 会补上对应文件头，
所以完整页面 = 该文件头 + 渲染出的片段。

`pure compile --plain` 会把同一个页面写成无依赖视图文件，因此没有安装 purephp 的部署也能
渲染；两种形态下控制器传入同一份 bindings。

## 绑定器 API

`component()` 是底层助手的便捷形式：

- `register(Card(...), $factory)` 注册一个单元，名字与文件从调用函数派生。
- `Registry::component($nameOrPath)` 返回单元或 shape 文件的
  `Closure(array $data): string` 绑定器，便于自己持有或传递。

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

`Registry::component()` 按名字或路径缓存绑定器，因此注册过的单元永远不需要自己写
`static` 变量。

## 缓存

- 单元文件旁存在不早于它的 `*.pure.php` 产物时，直接由产物提供服务；工厂与 Shape 树完全不
  会被触碰。
- `Registry::component()` 在同一个编译 generation 内按名字或路径缓存绑定器。
- `Compile::cachePath($dir)`——请求加载已生成的 renderer，而不是重新生成。
- `pure compile --check` 让 CI 把过期产物拦下来；长驻 worker 会把已加载的 renderer 留在
  内存里，产物在那里是可选项。

开启 opcache 后，加载整页组件产物约每个 0.5µs（见 `bench/README.md`），因此「产物 +
opcache」就是生产路径。

## 即时渲染（片段）

一次性片段可以完全跳过 shape，直接渲染标签树：

```php
<?php

div(h2('Title'), p('Content'))->class('card')->print();
```

只建议用于片段与调试；生产组件应当编译模板，让转义与结构成本只付一次。

## Slot 参考

组件是函数；Slot 是模板*内部*的词汇：`Slot::value()`、`Slot::raw()`、`Slot::child()`、
`Slot::each()` 与 `Slot::if()`。完整的数据绑定参考见
[Props 与 Slot](/zh/guide/props)。

## 下一步

- [编译渲染](/zh/guide/compiled)——组件模板如何编译与缓存
- [产物与部署](/zh/guide/artifacts)——产物、缓存与无依赖视图
- [Props 与 Slot](/zh/guide/props)——完整的数据绑定参考
- [事件](/zh/guide/events)——事件属性与浏览器端处理器
