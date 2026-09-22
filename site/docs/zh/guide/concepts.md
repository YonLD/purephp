# 基本概念

**前置**：[快速开始](/zh/guide/getting-started)；**本页**：Tag 树、Shape、Slot、组件与数据作用域。

本指南介绍 PurePHP 的核心概念：先讲标签树与 Slot，Shape 是组件渲染的不含数据的模板，
而数据驱动输出的推荐方式——组件（Component）——放在本页靠后介绍。

## 选择路径

一条规则覆盖两条路径：

- **输出由数据驱动 → 组件**：模板是不含数据的 `Slot` 占位符树，props 按请求绑定普通数据——见
  [组件](/zh/guide/components)。页面推荐走这条路径。
- **代码片段、原型或调试输出 → 立即渲染**：用真实值构建树并调用 `print()` 或 `render()`。

| 动作 | `Tag`（即时） | `Shape`（编译） |
| --- | --- | --- |
| 字符串 | `render()` | `$shape($data)` |
| 输出 | `print()` | `print($data)` |
| 文件 | `save($path)` | `save($path, $data)` |
| 调试 | `toJSON()` | `compile()->source` |

每个名字只出现一次：`render()` 返回字符串，`print()` 输出，`save()` 写文件（并补上根标签的文档声明），`toJSON()` / `source` 暴露结构用于调试。

## 标签树

PurePHP 用 PHP 对象表示 HTML。标签辅助函数构建树，方法链式调用设置属性：

```php
<?php

use function Pure\HTML\{div, h1, p};

$element = div(
    h1('Title'),
    p('Content')
)->class('container');

echo $element; // <div class="container"><h1>Title</h1><p>Content</p></div>
```

渲染时文本子节点与属性值会被转义；`Raw` 子节点原样输出。包含数据的标签树通过 `render()`、`print()` 或 `__toString()` 立即渲染。这条路径适合代码片段与调试。

## Shape 与 Slot

**Shape** 是同样的树，但*不含数据*：动态值被替换为 `Slot` 占位符。Shape 描述结构，数据稍后到达。
组件的模板正是一棵 Shape——本节先单独展示这种形态，让 Slot 词汇自成一体，本页靠后的组件一节再把它包装成推荐的单元。

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, p};

$shape = Compile::shape(
    div(
        h1(Slot::value('heading')),
        p(Slot::value('lead'))
    )->class('container')
);
```

Slot 类型：

| Slot | 绑定 | 是否创建嵌套作用域 |
| --- | --- | --- |
| `Slot::value()` | 标量 / `null` / `Stringable` | 否——子节点位转义为文本；属性位按 `setAttr()` 语义（`true`→`name="name"`，`false`/`null` 省略） |
| `Slot::raw()` | 可字符串化的值（或这类值的可迭代集合），原样输出 | 否 |
| `Slot::child()` | 数组 | 是 |
| `Slot::each()` | 数组的可迭代集合 | 是，逐项 |
| `Slot::if()` | 真值条件 | 否（各分支共享作用域） |

Slot 名字始终是**数据键**（也是错误路径），而不是标签名或属性名：在 `a(Slot::value('label'))->class(Slot::value('classList'))` 中，文本绑定 `label`，class 属性绑定 `classList`，而 `a` 与 `class` 来自树本身。

## 编译

`Compile::shape()` 把一棵树包装为 `Shape`。首次渲染会将它编译成扁平的 PHP 闭包：静态标记变成字面量字符串，转义只做一次，运行时只剩 Slot 的工作。

```php
<?php

$shape([
    'heading' => 'Welcome',
    'lead' => 'Compiled rendering',
]);
```

关键特性：

- **每个进程只编译一次**——把 Shape 记忆化到组件函数内的 `static` 变量中，绝不要在请求处理器中构建 Shape。标准 PHP-FPM 下 `static` 每个请求都会重置，因此请启用 `Compile::cachePath()`，让请求加载已编译的渲染器而不是重新生成。
- **输出逐字节一致**——编译路径与 `render()` 共用同一份转义实现。
- **可选的磁盘缓存**——`Compile::cachePath($dir)` 会存储编译后的渲染器，让已预热的 worker 直接加载代码而不是生成代码。

`Shape::id()` 是结构性指纹（标签、属性、Slot 与嵌套 Shape），无需编译即可获得；它决定磁盘缓存
的文件名，也是生成代码在内存中记忆化时的键。预编译产物把指纹记在头部注释里，而
`pure compile --check` 是通过把产物与现场生成的源码逐字节比对来识别过期的。组件的解析依据是
注册名或单元文件，而不是它。

## 数据绑定与作用域

渲染 Shape 时绑定普通数据：

```php
<?php

$list = Compile::shape(ul(Slot::each('items', li(Slot::value('title')))));

$list(['items' => [['title' => 'a'], ['title' => 'b']]]);
```

`Slot::child()` 与 `Slot::each()` 会建立嵌套作用域，因此在 `li` 内部，Slot `title` 针对当前项解析。缺失必填键会抛出带完整路径的 `Pure\Core\MissingSlotException`，错误信息会建议最接近的已提供键名或列出该作用域实际提供的键；可选数据请使用 `->default($value)` 或 `->required(false)`——完整规则见[缺失数据](/zh/guide/props#缺失数据)。

## 组件

Component 是 Shape 的包装与高级用法：一个 `*.cmp.php` 单元把返回 `Pure\Component\Call`
的调用函数、紧挨着它渲染的模板（一棵 Shape），以及放在 `prepare()` 钩子里的类型化 prop
契约放在一起。单元注册一个惰性工厂，因此 `pure compile` 可以预编译模板，而请求只加载产物：

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
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);

echo Card()->title('Title')->content('Content');
```

props 在调用上像标签属性一样链式设置，children 传给调用本身，`->render()`（或字符串转换）
产出标记；`prepare()` 的参数就是类型化 prop 契约，PHP 会强制它们的类型。

组合方式见[组件](/zh/guide/components)，缓存与每请求守卫见[编译渲染](/zh/guide/compiled)，
产物与生产部署见[产物与部署](/zh/guide/artifacts)。

## 状态管理

状态就是普通 PHP：值作为数据传入 Shape。

### 简单状态

```php [components/Counter.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\span;

function Counter(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Counter(...),
    factory: static fn () => span(Slot::value('count'))->id('counter'),
    prepare: static function (int $count): array {
        return ['count' => $count];
    }
);

echo Counter()->count(0);
```

### 全局状态

任何 PHP 存储方案都可以；组装好数据数组后渲染：

```php
<?php

class Store
{
    private static array $state = [];

    public static function set(string $key, mixed $value): void
    {
        self::$state[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        return self::$state[$key] ?? null;
    }
}

Store::set('user', ['name' => 'John']);
$user = Store::get('user');
```

## 条件与混合列表

上文的主表覆盖日常 Slot。列表项需要不同标记时，在数据层分派：逐项调用合适的组件函数，
把拼好的标记交给 raw Slot。完整示例见编译渲染指南的[混合列表](/zh/guide/compiled#混合列表)。

## 下一步

- [Props 与 Slot](/zh/guide/props) - 数据如何绑定到 Shape
- [组件](/zh/guide/components) - Component：Shape 的包装与高级用法
- [编译渲染](/zh/guide/compiled) - 组件模板如何被编译
- [产物与部署](/zh/guide/artifacts) - `pure compile` 产物与生产部署
