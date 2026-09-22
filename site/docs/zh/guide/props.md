# Props 与 Slot

**前置**：[基本概念](/zh/guide/concepts)；**本页**：Slot 类型、修饰符与数据绑定参考。

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

div('Content')->class('container', 'mt-4')->id('main');
```

### 数据属性与 ARIA 属性

包含连字符的属性名使用下划线，因为 `-` 在 PHP 方法名中无效：

```php
<?php

div('Content')
    ->data_id('123')      // data-id="123"
    ->data_type('card')   // data-type="card"
    ->aria_label('Card'); // aria-label="Card"
```

### 布尔属性

值为 `true` 时，属性以自身名称作为值渲染；`false` 与 `null` 则省略该属性：

```php
<?php

input()->type('checkbox')->checked(true);  // checked="checked"
input()->type('checkbox')->checked(false); // no checked attribute
```

`Slot::value()` 在渲染时遵循同样的规则，因此静态属性与动态属性不会出现语义偏差：绑定的 `false` 省略该属性，绑定的 `true` 渲染为 `checked="checked"`。

## 动态 props

动态属性值使用 `Slot::value()`。参数是数据键而不是属性名——属性名来自 setter，因此 `->class(Slot::value('classList'))` 会从数据中取 `classList` 并写入 `class`。`null` 值会在渲染时省略该属性（绑定的 `false` 行为相同），条件属性也是以此实现的：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

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
| `Slot::value($name)` | 可字符串化；`null` 仅在可选 Slot 或属性 Slot 中可用 | 位置决定语义：子节点位转义为文本（`true` 为 "1"；必填 Slot 拒绝 `null`）；属性位遵循 `setAttr()`（`true` 渲染 `name="name"`，`false`/`null` 省略该属性） |
| `Slot::raw($name)` | 可字符串化值，或这类值的可迭代集合 | 原样输出，绝不转义；集合按顺序拼接 |
| `Slot::child($name, $shape)` | 数组 | 为 `$shape` 创建嵌套作用域 |
| `Slot::each($name, $shape)` | 数组的可迭代集合 | 逐项渲染 `$shape` |
| `Slot::if($name, $then, $else = null)` | 真值判断 | 渲染分支；缺失的键为 false |

## 修饰符

```php
<?php

use Pure\Core\Slot;

Slot::value('subtitle')->required(false);   // 键缺失时渲染为空
Slot::value('subtitle')->default('—');       // 键缺失时的回退值
```

- `required(false)` 使 Slot 可选；键缺失与显式传入 `null` 都渲染为空（属性位则省略该属性）。
- 必填的值 Slot 与 raw Slot 既不接受缺失的键，也不接受显式的 `null`。
- `default($value)` 为缺失的键提供回退值，并使 Slot 可选。回退值会被内联进编译后的渲染器，因此必须是值类型：`null`、标量或由值类型组成的数组。
- `Slot::if()` 会以 `LogicException` 拒绝这两个修饰符：它的条件是真值判断，回退为 `false`。

## 值转换与转义

值 Slot 与 raw Slot 接受标量和 `Stringable` 对象——包括 `Raw`，它不需要强制转换——可选 Slot 还接受 `null`。使用前会先转换为字符串；数组和其他对象会抛出 `InvalidArgumentException`，并在信息中给出完整 Slot 路径。raw Slot 更进一步，还接受可字符串化值的可迭代集合，并按顺序拼接它们。

- `Slot::value()` 在子节点位使用 `htmlspecialchars(..., double_encode: false)` 转义，因此你已经转义过的实体（`&copy;`）会保持不变。
- `Slot::value()` 在属性位使用 `double_encode: true` 转义。
- `Slot::raw()` 不执行任何转义——请仅对受信任的标记使用。
- 无效的 UTF-8 会被替换为替换字符，而不是产生损坏的输出。

## 缺失数据

必填 Slot 会抛出带完整路径的 `Pure\Core\MissingSlotException`。错误信息让拼写错误可见：
它会建议最接近的已提供键名，或列出该作用域实际提供的键；必填的值 Slot 与 raw Slot 显式传入
`null` 时也会失败（属性 Slot 仍然按 `null` 省略自身）：

```php
<?php

$shape = Compile::shape(div(Slot::value('title'), Slot::value('body')));

$shape(['titel' => 'x', 'body' => 'b']);
// slot 'title' is required but was not provided; did you mean 'titel'?
$shape(['title' => null, 'body' => 'b']);
// slot 'title' is required but was null.
$shape([]);
// slot 'title' is required but was not provided.
```

路径用于标识嵌套作用域：`card.title` 表示 `Slot::child()` Slot，`items[].title` 表示列表项。

## 派生 props

子组件从其 Slot 名对应的嵌套数据中读取 props，因此请在数据层完成派生，再交给渲染：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

$badge = Compile::shape(span(Slot::value('label'))->class('badge'));

$shape = Compile::shape(div(Slot::child('user', $badge)));

$shape(['user' => ['label' => 'ADA']]); // <div><span class="badge">ADA</span></div>
```

嵌套 shape 也可以是裸标签树——`Slot::child('user', span(Slot::value('label')))` 同样可行；
只有需要单独构建并复用嵌套树时才要写 `Compile::shape()`。

`Slot::each()` 同理：每个元素本身就是该项的作用域，所以控制器先把原始行整理成 props 数组列表再渲染。

## 组件 props 契约

由于 Shape 不含数据，组件的数据契约就存在于它的 Slot 中。请在组件旁边记录该契约，并把绑定数组集中放在一处；渲染时缺失必填键会带完整路径明确报错。

## 下一步

- [组件](/zh/guide/components) - Component：Shape 的包装与高级用法
- [编译渲染](/zh/guide/compiled) - 列表、条件、缓存与限制
- [产物与部署](/zh/guide/artifacts) - `pure compile` 产物与生产部署
- [基本概念](/zh/guide/concepts) - Shape、作用域与编译
- [事件](/zh/guide/events) - 事件属性与浏览器端处理器
