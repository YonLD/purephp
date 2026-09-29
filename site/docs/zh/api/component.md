---
title: 组件 API
description: PurePHP 组件公共入口、Call 行为、Registry 边界、契约属性、Template 与 PHPStan 集成。
---

# 组件 API

本页介绍 `Pure\Component` 周围的组件接口：`component()`、`register()`、`Call`、
契约属性与 `#[Template]`，以及 PHPStan 集成。`Slot` 构造器与值类型见
[Props 与 Slot](/zh/guide/props#slot-参考)；组合方式见[组件指南](/zh/guide/components)。

## 公共接口一览

| 接口 | 作用 | 稳定性 |
| --- | --- | --- |
| `Pure\Component\component()` | 为注册名或单元/模板路径创建链式 `Call`。 | 公共辅助函数。 |
| `Pure\Component\register()` | 从调用函数和惰性 factory 注册命名单元，`prepare()` 钩子可选。 | 公共辅助函数。 |
| `Pure\Component\Call` | 链式 props、children、渲染与字符串转换。 | 公共结果类型；请通过 `component()` 创建。 |
| `Pure\Component\Prop` | 声明 `prepare()` 参数的 Slot、item shape、必填性或废弃提示。 | `pure check` 契约。 |
| `Pure\Component\Trusted` | 标记携带可信标记的 `prepare()` 参数。 | `pure check` 与开发守卫声明。 |
| `Pure\Component\Binds` | 声明 `prepare()` 钩子或 bindings helper 返回的键。 | `pure check` 契约。 |
| `Pure\Compile\Template` | 标记单元旁边的模板构建器。 | `pure compile` / `pure check` 集成。 |
| `Pure\StaticAnalysis\UnknownComponentRule` | 报告没有对应注册项的字面量组件调用。 | 公共 PHPStan 规则（`@api`）。 |
| `Pure\Component\Registry` | 辅助函数与编译器使用的内部注册表。 | `@internal`；不要把其方法签名当作应用契约。 |

## `component()` 与 `Call`

公共辅助函数的签名为：

```php
function component(string $name, mixed ...$children): Call;
```

`$name` 可以是已注册组件名、`*.cmp.php` 单元路径或 `*.shape.php` 模板路径。单元
自己的调用函数应传入 `__FUNCTION__`，让名称只写一次；只有模板读取 `children` Slot 的
单元才声明 children，其余单元不接收 children：

```php
function Card(): Call
{
    return component(__FUNCTION__);
}
```

`Call` 实现 `Pure\Core\Markup`，因此可以像标签子节点一样嵌套，字符串转换时会
惰性渲染单元。

### Props 与 children

一个 prop 就是一条命名 binding。常见链式形式如下：

```php
echo Card()
    ->title('Free')
    ->content('Everything you need');
```

- `__call($prop, $value)` 设置一个 prop，并返回同一个调用对象。
- `class(...$values)` 与 `style($value)` 使用和标签 setter 相同的合并规则。
- `props(array $values)` 一次设置多个 prop，数组键就是 prop 名称。
- `null` 表示不设置该 prop，与 `Tag::setAttr()` 一致。
- 不能把 `Slot` 当作 prop；请绑定值，并在模板中读取 Slot。
- children 传给调用函数，而不是调用 `children()` setter。它们绑定保留的
  `children` Slot，模板用 `Slot::raw('children')` 读取，所以声明要跟随模板：
  转发给没有该 Slot 的模板会在渲染时抛异常，而不接收 children 的调用函数会
  静默丢弃。

渲染 children 的单元要声明它们并转发给 `component()`，调用时把它们作为参数传入：

```php [components/Button.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button as htmlButton, span};

function Button(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Button(...), static fn () =>
    htmlButton(Slot::raw('children'), ' ', Slot::value('label'))->class('btn')
);

echo Button(span('Save'))->label('now');
```

children 遵循标签的规则：`Tag` 或 `Markup` 子节点按标记原样输出，字符串子节点转义
为文本，`Slot` 子节点会被拒绝。

该类提供 `render(): string` 与 `__toString(): string`。构造函数标记为 internal；
请用 `component()`，不要直接实例化 `Call`。

## 注册单元

公共辅助函数签名为：

```php
function register(
    Closure $call,
    ?Closure $factory = null,
    bool $override = false,
    ?Closure $prepare = null
): void;
```

虽然类型签名允许 `null`，组件注册仍必须提供非空 factory；传入 `null` 会抛出
`InvalidArgumentException`。

推荐的调用函数形式通过反射派生组件名与单元文件：

```php
register(Card(...),
    factory: static fn () => div(
        h2(Slot::value('title')),
        p(Slot::value('content'))
    )->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);
```

factory 是惰性的：注册只保存它；新鲜的 `*.pure.php` 产物可以直接服务单元，不必
调用 factory。`prepare()` 参数是类型化 prop 契约，返回数组绑定模板。一个单元文件
只注册一个组件；替换已有名称或文件需要 `override: true`。

PHPStan collector 也能识别直接调用 `Registry::register()`，但它不是推荐的应用 API。
优先使用 `register(Card(...), ...)`。

## 契约属性

这些属性由 `pure check` 和开发守卫读取，不会改变渲染器，也不会清理输入。

### `#[Prop]`

`#[Prop]` 修饰 `prepare()` 钩子的参数：

```php
use Pure\Component\Prop;

prepare: static function (
    #[Prop(slot: 'title')] string $text,
    #[Prop(item: 'value')] array $features,
    #[Prop(required: false)] ?string $class = null,
    #[Prop(deprecated: 'use class()')] ?string $style = null,
): array {
    return [
        'title' => $text,
        'features' => $features,
        'class' => $class,
        'style' => $style,
    ];
}
```

- `slot` 指定 prop 填充的 binding，默认是参数名。
- `item` 指定列表 prop 每一项填充的 Slot。
- `required` 声明调用方义务；与签名矛盾会被报告。
- `deprecated` 是迁移提示，会显示在绑定该 prop 的调用点。

当 `prepare()` 不是返回一个可读的数组字面量时，声明的 Slot 仍可供 checker 使用，
必填 Slot 检查不会因此悄悄消失。

### `#[Trusted]`

`#[Trusted]` 标记携带已渲染标记的 prop：

```php
use Pure\Core\Markup;
use Pure\Component\Trusted;

prepare: static function (#[Trusted] Markup $icon): array
{
    return ['icon' => $icon];
}
```

`pure check` 会验证它绑定 raw Slot，且没有同时作为文本 Slot 读取。开发守卫会在
调用值不是 `Pure\Core\Markup` 时告警。这是信任声明，不是转义函数：只传入你已经
确认可信的标记。

如果 prop 名称和 Slot 名称不同，请同时使用 `#[Prop(slot: 'icon')]`。

### `#[Binds]`

当返回数组是动态组装时，用 `#[Binds]` 声明钩子或 helper 返回的键：

```php
use Pure\Component\Binds;

prepare: #[Binds('title', 'description')] static function (): array
{
    return PricingService::pricing();
}
```

该属性也可用于 bindings helper。checker 会把声明的键与模板比较，因此缺少必填键
或出现未声明键不会藏在 service 调用后面。渲染时不会读取它。

## `#[Template]`

`Pure\Compile\Template` 标记单元文件中的 Shape 构建器：

```php
use Pure\Compile\Compile;
use Pure\Compile\Shape;
use Pure\Compile\Template;
use Pure\Core\Slot;

use function Pure\HTML\{div, h2};

#[Template]
function CardShape(): Shape
{
    static $shape;

    return $shape ??= Compile::shape(
        div(h2(Slot::value('title')))->class('card')
    );
}
```

`pure compile --list` 会把标记的构建器列为 `(template)`，同时列出注册的
`(component)` 单元和独立 `(shape)` 文件。`pure check` 会校验声明的返回类型是
`Pure\Compile\Shape` 或标签。该标记属于工具元数据：它不会注册第二个组件，也不会
在渲染时执行函数。

## Registry 边界

`Pure\Component\Registry` 明确标记为 `@internal`。它保存单元元数据、产物与 binder，
编译器和 `Call` 实现会在内部使用它。旧指南或内部代码中可能出现以下方法，但应用
代码不应把它们的签名当作兼容性承诺：

- `Registry::register()` 与 `Registry::prepare()`；
- `Registry::slots()`、`Registry::names()`、`Registry::unitsFor()` 以及 reset
  辅助方法；
- `Registry::component()` 返回的 binder。

应用代码请使用 `register()` 和 `component()`。如果确实需要底层 binder，请把它隔离在
自己的适配器后，避免未来内部变化泄漏到组件契约中。

## PHPStan 集成

包内提供公共静态分析类，请在自己的 `phpstan.neon` 中注册它们：

- `Pure\StaticAnalysis\ComponentCallCollector` 与
  `Pure\StaticAnalysis\RegistryCallCollector` 收集注册项和字面量调用；
- `Pure\StaticAnalysis\UnknownComponentRule` 报告 `component('Crad')` 这类字面量
  调用，而被分析项目注册的是 `Card`；
- 该规则带 `@api` 标记，并报告标识符 `purephp.unknownComponent`。

```
# phpstan.neon
services:
    -
        class: Pure\StaticAnalysis\ComponentCallCollector
        tags:
            - phpstan.collector
    -
        class: Pure\StaticAnalysis\RegistryCallCollector
        tags:
            - phpstan.collector

rules:
    - Pure\StaticAnalysis\UnknownComponentRule
```

请分析整个项目而不是单个路径，因为只有所有文件都分析完成后，收集到的注册项才完整。
单文件分析会跳过该规则。路径与 `component(__FUNCTION__)` 会按文件和函数上下文解析，
不会被当成未知字面量。组件名来自数据时，请让动态名称留在应用边界，并在边界校验数据。

## 相关页面

- [组件](/zh/guide/components)——组合、props、children 与页面
- [编译 API](/zh/api/compile)——Shape、Renderer、Slot、缓存与产物 API
- [故障排查](/zh/guide/troubleshooting)——Registry、prop 与产物症状
- [升级](/zh/guide/upgrading)——升级与重建步骤
