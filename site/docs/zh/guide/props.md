# Props 与 Slot

**前置**：[核心概念](/zh/guide/concepts)；**本页**：Slot 类型、修饰符与数据绑定参考。

在 PurePHP 中，“props”有两种形式：

- **静态 props**——构建组件时已知的值（函数参数、字面量属性）。
- **动态 props**——渲染时绑定的值：`Slot` 占位符。

本页是数据绑定参考；渲染管线本身请参见[编译渲染](/zh/guide/compiled)。

## 静态 props

### HTML 属性

属性通过方法链式调用设置，并以字面量形式存储在 Shape 中：

```php
<?php

use Pure\Compile\Compile;

use function Pure\HTML\div;

$shape = Compile::shape(
    div('Content')
        ->id('main')
        ->class('container')
        ->style('background: #fff;')
);

$shape([]);
```

`className()` 是 `class()` 的别名，并且可以向 `class()` 传入多个值：

```php
<?php

use function Pure\HTML\div;

div('Content')->class('container', 'mt-4')->id('main');
```

### 数据属性与 ARIA 属性

包含连字符的属性名使用下划线，因为 `-` 在 PHP 方法名中无效：

```php
<?php

use function Pure\HTML\div;

div('Content')
    ->data_id('123')
    ->data_type('card')
    ->aria_label('Card');
```

### 布尔属性

值为 `true` 时，属性以自身名称作为值渲染；`false` 与 `null` 则省略该属性：

```php
<?php

use function Pure\HTML\input;

input()->type('checkbox')->checked(true);  // checked="checked"
input()->type('checkbox')->checked(false); // no checked attribute
```

后续用 `false` 或 `null` 调用 setter 会被忽略，并不会删除之前已经设置的属性。例如
`input()->checked(true)->checked(false)` 仍会渲染 `checked="checked"`。如果后面的值
必须清除属性，请一次设置最终值，或重新创建标签。

`Slot::value()` 在渲染时遵循同样的规则，因此静态属性与动态属性不会出现语义偏差：绑定的 `false` 省略该属性，绑定的 `true` 渲染为 `checked="checked"`。

## 动态 props

动态属性值使用 `Slot::value()`。参数是数据键而不是属性名——属性名来自 setter，因此 `->class(Slot::value('classList'))` 会从数据中取 `classList` 并写入 `class`。`null` 值会在渲染时省略该属性（绑定的 `false` 行为相同），条件属性也可以通过这种方式实现：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\button;

$shape = Compile::shape(
    button('Save')->class(Slot::value('classList'))->disabled(Slot::value('disabled'))
);

$shape(['classList' => 'btn btn-primary', 'disabled' => null]);
// <button class="btn btn-primary">Save</button>
$shape(['classList' => 'btn btn-primary', 'disabled' => 'disabled']);
// <button class="btn btn-primary" disabled="disabled">Save</button>
```

## Slot 参考

| Slot | 值 | 行为 |
| --- | --- | --- |
| `Slot::value($name)` | 可字符串化；只有可选值或属性位才接受有效的 `null` | 位置决定语义：子节点位转义为文本（`true` 为 "1"；子节点/文本位的必填值 Slot 拒绝 `null`）；属性位遵循 `setAttr()`（`true` 渲染 `name="name"`，`false`/`null` 省略该属性） |
| `Slot::raw($name)` | 可字符串化值，或这类值的可迭代集合；只有可选 Slot 才接受 `null` | 原样输出，绝不转义；集合按顺序拼接；必填 raw Slot 拒绝 `null` |
| `Slot::child($name, $shape)` | 数组 | 为 `$shape` 创建嵌套作用域；即使 Slot 可选，值仍必须是数组 |
| `Slot::each($name, $shape)` | 条目的可迭代集合 | 逐项渲染 `$shape`；即使 Slot 可选，值仍必须可迭代 |
| `Slot::if($name, $then, $else = null)` | 真值判断 | 渲染分支；缺失的键为 false |

选择列表 Slot 时，看标记是否已经渲染：`Slot::raw()` 拼接已渲染的标记，`Slot::each()`
则按数据项逐个渲染 item Shape。

列表项通常是一个作用域，即提供条目 Shape 所读各键的数组。当条目 Shape 恰好渲染一个
Slot 时，该项也可以直接是这个 Slot 的值，字符串列表不必再包一层：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{li, ul};

$list = Compile::shape(ul(Slot::each('items', li(Slot::value('label')))));

$list(['items' => ['a', 'b']]);                   // 两种写法渲染结果相同
$list(['items' => [['label' => 'a'], ['label' => 'b']]]);
```

条目 Shape 读取多个 Slot、把唯一的 Slot 读作嵌套的 `Slot::child()` 或
`Slot::each()`、或只读作条件，或者根本不读 Slot 时，都没有单一的值可绑定，其条目仍是
作用域；此时传入标量，错误信息会列出该 Shape 期望的键。

## 修饰符

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

$userShape = Compile::shape(div(Slot::value('name')));
$itemShape = Compile::shape(div(Slot::value('label')));
Slot::value('subtitle')->required(false);   // 键缺失时渲染为空
Slot::value('subtitle')->default('—');       // 键缺失时的回退值
Slot::child('user', $userShape)->default([]);
Slot::each('items', $itemShape)->default([]);
```

- `required(false)` 使值 Slot 与 raw Slot 可选；键缺失与显式 `null` 会使用空值/回退值。
  必填的值 Slot 与 raw Slot 在子节点/文本位既不接受缺失的键，也不接受显式 `null`。
  属性位的 `Slot::value()` 遵循 `setAttr()`，会省略 `null`；raw Slot 不能用作属性值。
- 对 `child` 与 `each`，修饰符只控制缺失键的回退，不改变容器类型：child 的有效值仍必须是
  数组，each 的有效值仍必须可迭代。因此 `default(null)` 在渲染该分支时仍会失败；希望
  留空时请使用 `default([])`（或其它合法容器）。静态分析生成的 docblock 仍可能把可选容器
  标为 `|null`，这只描述缺失数据，不会改变运行时的类型检查。
- `default($value)` 为缺失的键提供回退值，并使 Slot 可选。回退值会被内联进编译后的渲染器，因此必须是值类型：`null`、标量或由值类型组成的数组。
- `Slot::if()` 会以 `LogicException` 拒绝这两个修饰符：它的条件是真值判断，回退为 `false`。

## 值转换与转义

值 Slot 与 raw Slot 接受标量和 `Stringable` 对象——包括 `Raw`，它不需要强制转换——可选的值/raw Slot 也会接受有效的 `null` 回退。使用前会先转换为字符串；数组和其他对象会抛出 `InvalidArgumentException`，并在信息中给出完整 Slot 路径。raw Slot 更进一步，还接受可字符串化值的可迭代集合，并按顺序拼接它们；元素不可字符串化时会报告偏移，例如 `slot 'items[2]' must be stringable`。即使 Slot 可选，`child` 与 `each` 的值仍会做类型检查：child 必须是数组，each 必须可迭代。

- `Slot::value()` 在子节点位使用 `htmlspecialchars(..., double_encode: false)` 转义，因此你已经转义过的实体（`&copy;`）会保持不变。
- `Slot::value()` 在属性位使用 `double_encode: true` 转义。
- `Slot::raw()` 不执行任何转义——请仅对受信任的标记使用。
- 无效的 UTF-8 会被替换为替换字符，而不是产生损坏的输出。

转义保护的是文本与属性语法，并不是完整的 URL 或脚本策略。请校验 `href`/`src` 的协议，
不要绑定不可信的 `on*` 事件或 `style` 属性，限制动态标签名；页面包含 `Raw` 标记时，还应
配置合适的 Content Security Policy。

## 缺失数据

必填 Slot 会抛出带完整路径的 `Pure\Core\MissingSlotException`。错误信息让拼写错误可见：
它会建议最接近的已提供键名，或列出该作用域实际提供的键；必填的值 Slot 与 raw Slot 显式传入
`null` 时也会失败（属性 Slot 仍然按 `null` 省略自身）：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\MissingSlotException;
use Pure\Core\Slot;

use function Pure\HTML\div;

$shape = Compile::shape(div(Slot::value('title'), Slot::value('body')));

try {
    $shape(['titel' => 'x', 'body' => 'b']);
} catch (MissingSlotException $error) {
    // slot 'title' is required but was not provided; did you mean 'titel'?
    echo $error->getMessage(), PHP_EOL;
}

try {
    $shape(['title' => null, 'body' => 'b']);
} catch (MissingSlotException $error) {
    // slot 'title' is required but was null.
    echo $error->getMessage(), PHP_EOL;
}

try {
    $shape([]);
} catch (MissingSlotException $error) {
    // slot 'title' is required but was not provided.
    echo $error->getMessage(), PHP_EOL;
}
```

路径用于标识嵌套作用域：`card.title` 表示 `Slot::child()` Slot，`items[].title` 表示列表项。

## 派生 props

子组件从其 Slot 名对应的嵌套数据中读取 props，因此请在数据层完成派生，再交给渲染：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, span};

$badge = Compile::shape(span(Slot::value('label'))->class('badge'));

$shape = Compile::shape(div(Slot::child('user', $badge)));

$shape(['user' => ['label' => 'ADA']]); // <div><span class="badge">ADA</span></div>
```

嵌套 shape 也可以是裸标签树——`Slot::child('user', span(Slot::value('label')))` 同样可行；
只有需要单独构建并复用嵌套树时才要写 `Compile::shape()`。

`Slot::each()` 同理：每个元素本身就是该项的作用域，所以控制器先把原始行整理成 props
数组列表再渲染；条目 Shape 只渲染一个 Slot 时，直接传原始值也可以。

## 组件 props 契约

由于 Shape 不含数据，组件的数据契约就存在于它的 Slot 中。请在组件旁边记录该契约，并把绑定数组集中放在一处；渲染时缺失必填键会带完整路径明确报错。

## 下一步

- [组件](/zh/guide/components) - Component：Shape 的包装与高级用法
- [编译渲染](/zh/guide/compiled) - 列表、条件、缓存与限制
- [产物与部署](/zh/guide/artifacts) - `pure compile` 产物与生产部署
- [核心概念](/zh/guide/concepts) - Shape、作用域与编译
- [事件](/zh/guide/events) - 事件属性与浏览器端处理器
