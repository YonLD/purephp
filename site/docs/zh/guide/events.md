# 事件

**前置**：[基本用法](/zh/guide/basic-usage)；**本页**：事件属性与浏览器端处理器。

本指南解释如何在 PurePHP 组件中处理事件。

::: tip 事件属性属于标签 API
诸如 `->onclick(...)` 这样的事件属性属于标签 API，依然有效；它们是静态属性，因此在 Shape 上的设置方式相同。对于页面，应构建 Shape 并编译它们——参见[编译渲染](/zh/guide/compiled)。下面的多数示例使用标签 API，它仍是用于代码片段和调试的即时渲染路径。
:::

## 基本事件处理

PurePHP 通过属性方法支持所有标准 HTML 事件。事件处理程序通常是作为字符串传递的 JavaScript 函数：

```php
<?php

use function Pure\HTML\{div, button, input};

function BasicEvents()
{
    return div(
        button('Click me')
            ->onclick('handleClick()')
            ->class('btn'),
        input()
            ->type('text')
            ->oninput('handleInput(event)')
            ->placeholder('Type something...')
            ->class('input')
    )->class('events-container');
}

BasicEvents()->print();
```

## 鼠标事件

处理各种鼠标交互：

```php
<?php

use function Pure\HTML\div;

function MouseEvents()
{
    return div('Hover and click me!')
        ->onclick('console.log("Clicked!")')
        ->onmouseover('this.style.backgroundColor = "#f0f0f0"')
        ->onmouseout('this.style.backgroundColor = ""')
        ->onmousedown('this.style.transform = "scale(0.95)"')
        ->onmouseup('this.style.transform = "scale(1)"')
        ->style('padding: 20px; border: 1px solid #ccc; cursor: pointer; transition: all 0.2s;')
        ->class('mouse-events');
}

MouseEvents()->print();
```

## 键盘事件

处理键盘输入：

```php
<?php

use function Pure\HTML\{div, input, p};

function KeyboardEvents()
{
    return div(
        p('Type in the input below:'),
        input()
            ->type('text')
            ->onkeydown('handleKeyDown(event)')
            ->onkeyup('handleKeyUp(event)')
            ->oninput('handleInput(event)')
            ->placeholder('Press keys...')
            ->class('keyboard-input'),
        p()->id('key-display')->style('margin-top: 10px; font-family: monospace;')
    )->class('keyboard-events');
}

echo KeyboardEvents();
?>
<script>
function handleKeyDown(event) {
    document.getElementById('key-display').textContent =
        `Key pressed: ${event.key} (Code: ${event.code})`;
}

function handleKeyUp(event) {
    console.log('Key released:', event.key);
}

function handleInput(event) {
    console.log('Input value:', event.target.value);
}
</script>
```

## 表单事件

处理表单交互：

```php
<?php

use function Pure\HTML\{form, div, label, input, button, p};

function FormEvents()
{
    return div(
        form(
            div(
                label('Username:')->for('username'),
                input()
                    ->type('text')
                    ->id('username')
                    ->name('username')
                    ->onchange('handleChange(event)')
                    ->required(true)
            )->class('form-group'),
            div(
                label('Email:')->for('email'),
                input()
                    ->type('email')
                    ->id('email')
                    ->name('email')
                    ->onchange('handleChange(event)')
                    ->required(true)
            )->class('form-group'),
            button('Submit')
                ->type('submit')
                ->class('submit-btn')
        )
        ->onsubmit('handleFormSubmit(event)')
        ->class('event-form'),
        p()->id('form-status')->style('margin-top: 10px; color: #666;')
    )->class('form-events');
}

echo FormEvents();
?>
<script>
function handleChange(event) {
    console.log(`${event.target.name} changed to: ${event.target.value}`);
}

function handleFormSubmit(event) {
    event.preventDefault(); // 阻止实际表单提交
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    document.getElementById('form-status').textContent =
        `Form submitted with: ${JSON.stringify(data)}`;

    console.log('Form data:', data);
}
</script>
```

## 事件委托

使用事件委托高效处理事件：

```php
<?php

use function Pure\HTML\{div, button};

function EventDelegation()
{
    $buttons = [];
    for ($i = 1; $i <= 5; $i++) {
        $buttons[] = button("Button {$i}")
            ->data_id($i)
            ->class('delegated-btn');
    }

    return div(
        div('Click any button:')->style('margin-bottom: 10px;'),
        div(...$buttons)->class('button-group'),
        div()->id('delegation-output')->style('margin-top: 10px; color: #666;')
    )
    ->onclick('handleDelegatedClick(event)')
    ->class('delegation-container');
}

echo EventDelegation();
?>
<script>
function handleDelegatedClick(event) {
    if (event.target.classList.contains('delegated-btn')) {
        const buttonId = event.target.dataset.id;
        document.getElementById('delegation-output').textContent =
            `Clicked button ${buttonId}`;
        console.log('Clicked button:', buttonId);
    }
}
</script>
```

## 组件事件通信

事件处理程序写在组件的静态事件属性上；动态值通过 Slot 传入：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, button, p};

function Child(string $message): string
{
    static $render;
    $render ??= Compile::shape(
        div(
            p('Child Component'),
            button(Slot::value('message'))
                ->onclick("handleChildClick('Hello from child!')")
                ->class('child-btn')
        )
        ->class('child-component')
        ->style('border: 1px solid #ddd; padding: 10px; margin: 10px 0;')
    );

    return $render(['message' => $message]);
}

function ParentComponent(iterable|string $child): string
{
    static $render;
    $render ??= Compile::shape(
        div(
            p('Parent Component'),
            Slot::raw('child'),
            p()->id('parent-output')->style('margin-top: 10px; color: #666;')
        )->class('parent-component')
    );

    return $render(['child' => $child]);
}

echo ParentComponent(Child('Click me from child!'));
?>
<script>
function handleChildClick(message) {
    document.getElementById('parent-output').textContent =
        `Received from child: ${message}`;
    console.log('Child event:', message);
}
</script>
```

处理程序会作为静态属性编译进 Shape，因此处理程序名不能来自请求数据。完整流程参见[编译渲染](/zh/guide/compiled)。

## 自定义事件属性

处理任何 HTML 事件属性：

```php
<?php

use function Pure\HTML\{div, img};

function CustomEventAttributes()
{
    return div(
        div('Image with load event:'),
        img()
            ->src('https://via.placeholder.com/200x100')
            ->alt('Placeholder image')
            ->onload('console.log("Image loaded!")')
            ->onerror('console.log("Image failed to load")')
            ->style('display: block; margin: 10px 0;'),

        div('Div with focus events:'),
        div('Click to focus, then press Tab')
            ->tabindex('0')
            ->onfocus('this.style.outline = "2px solid blue"')
            ->onblur('this.style.outline = "none"')
            ->style('padding: 10px; border: 1px solid #ccc; margin: 10px 0;')
    )->class('custom-events');
}

CustomEventAttributes()->print();
```

## 事件处理最佳实践

### 1. 内联 vs 外部处理程序

```php
<?php

use function Pure\HTML\{div, button};

// 内联处理程序（适合简单操作）
$inlineButton = button('Inline Handler')
    ->onclick('alert("Hello from inline!")')
    ->class('btn');

// 外部处理程序（适合复杂逻辑）
$externalButton = button('External Handler')
    ->onclick('handleComplexAction()')
    ->class('btn');

div(
    $inlineButton,
    $externalButton
)->print();
?>
<script>
function handleComplexAction()
{
    // 复杂逻辑在这里
    console.log('Complex action executed');
    // ... 更多代码
}
</script>
```

### 2. 事件对象使用

```php
<?php

use function Pure\HTML\{div, button};

function EventObjectExample()
{
    return div(
        button('Get Event Info')
            ->onclick('showEventInfo(event)')
            ->class('btn'),
        div()->id('event-info')->style('margin-top: 10px; font-family: monospace;')
    )->class('event-object-example');
}

echo EventObjectExample();
?>
<script>
function showEventInfo(event) {
    const info = `
        Event Type: ${event.type}
        Target: ${event.target.tagName}
        Timestamp: ${event.timeStamp}
        Coordinates: (${event.clientX}, ${event.clientY})
    `;
    document.getElementById('event-info').textContent = info;
}
</script>
```

## 下一步

- [HTMX 集成](/zh/guide/htmx) - 了解如何与 HTMX 配合使用
- [组件](/zh/guide/components) - 深入学习组件开发
- [Props 与 Slot](/zh/guide/props) - Slot 类型与数据绑定
