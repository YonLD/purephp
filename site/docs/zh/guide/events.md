# 事件

**前置**：[基本用法](/zh/guide/basic-usage)；**本页**：无障碍浏览器事件、事件委托与符合 CSP 的监听器设置。

PurePHP 负责渲染 HTML，浏览器 JavaScript 负责交互。用 PHP 输出语义化控件和无障碍状态区域，再用 JavaScript 通过 `addEventListener` 附加行为。

::: tip 优先使用外部监听器
`->onclick(...)`、`->oninput(...)` 等标签方法依然有效：它们会写出内联的 `onclick` 或 `oninput` 属性。未允许 `'unsafe-inline'` 的 Content Security Policy 会阻止这类处理器。因此下面的示例不把行为写进元素属性，而统一使用 `addEventListener`。
:::

## 基本事件处理

把下面内容保存到已安装 PurePHP 的项目中的 `events.php`，然后运行 `php events.php`：

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{button, div, input, label, output, p};

echo div(
    label('Message')->for('event-message'),
    input()
        ->id('event-message')
        ->name('message')
        ->aria_describedby('event-help'),
    p('The status is announced after either control changes.')->id('event-help'),
    button('Show message')->type('button')->id('event-trigger'),
    output('Ready.')->id('event-status')->aria_live('polite')
)->class('events-container');
?>
<script>
const eventTrigger = document.getElementById('event-trigger');
const eventInput = document.getElementById('event-message');
const eventStatus = document.getElementById('event-status');

eventTrigger.addEventListener('click', () => {
    eventStatus.textContent = 'Message button activated.';
});

eventInput.addEventListener('input', event => {
    eventStatus.textContent = `Current message: ${event.target.value || '(empty)'}`;
});
</script>
```

输入框有真正的 `<label>`，操作使用语义化的 `<button type="button">`，`<output aria-live="polite">` 会在不移动键盘焦点的情况下播报更新。

## 鼠标事件

操作用 button，结果通过实时区域公开：

```php
<?php

use function Pure\HTML\{button, div, output};

echo div(
    button('Hover and click me')->type('button')->id('mouse-button'),
    output('Waiting for an event.')->id('mouse-status')->aria_live('polite')
)->class('mouse-events');
?>
<script>
const mouseButton = document.getElementById('mouse-button');
const mouseStatus = document.getElementById('mouse-status');

mouseButton.addEventListener('click', () => {
    mouseStatus.textContent = 'Clicked';
    mouseButton.style.backgroundColor = '#f0f0f0';
});

mouseButton.addEventListener('mouseover', () => {
    mouseButton.style.backgroundColor = '#e0e0e0';
});

mouseButton.addEventListener('mouseout', () => {
    mouseButton.style.backgroundColor = '';
});

mouseButton.addEventListener('mousedown', () => {
    mouseButton.style.transform = 'scale(0.95)';
});

mouseButton.addEventListener('mouseup', () => {
    mouseButton.style.transform = 'scale(1)';
});
</script>
```

## 键盘事件

保留可见标签、描述实时输出，并监听键盘输入：

```php
<?php

use function Pure\HTML\{div, input, label, output};

echo div(
    label('Keyboard input')->for('keyboard-input'),
    input()
        ->id('keyboard-input')
        ->type('text')
        ->aria_describedby('key-display'),
    output('Focus the input and press a key.')->id('key-display')->aria_live('polite')
)->class('keyboard-events');
?>
<script>
const keyboardInput = document.getElementById('keyboard-input');
const keyDisplay = document.getElementById('key-display');

function handleKeyDown(event) {
    keyDisplay.textContent = `Key pressed: ${event.key} (Code: ${event.code})`;
}

function handleKeyUp(event) {
    console.log('Key released:', event.key);
}

function handleInput(event) {
    console.log('Input value:', event.target.value);
}

keyboardInput.addEventListener('keydown', handleKeyDown);
keyboardInput.addEventListener('keyup', handleKeyUp);
keyboardInput.addEventListener('input', handleInput);
</script>
```

## 表单事件

每个控件都关联标签，并播报校验或提交状态：

```php
<?php

use function Pure\HTML\{button, div, form, input, label, output};

echo div(
    form(
        div(
            label('Username')->for('username'),
            input()->id('username')->name('username')->required(true)
        ),
        div(
            label('Email')->for('email'),
            input()->id('email')->name('email')->type('email')->required(true)
        ),
        button('Submit')->type('submit')
    )
        ->id('event-form')
        ->aria_describedby('form-status'),
    output('The form has not been submitted.')->id('form-status')->aria_live('polite')
)->class('form-events');
?>
<script>
const eventForm = document.getElementById('event-form');
const formStatus = document.getElementById('form-status');

eventForm.addEventListener('change', event => {
    console.log(`${event.target.name} changed to: ${event.target.value}`);
});

eventForm.addEventListener('submit', event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.target));
    formStatus.textContent = `Form submitted with: ${JSON.stringify(data)}`;
});
</script>
```

## 事件委托

一个监听器可以处理一组相关按钮，各按钮仍可独立使用键盘操作：

```php
<?php

use function Pure\HTML\{button, div, output};

$buttons = [];
for ($i = 1; $i <= 5; $i++) {
    $buttons[] = button("Button {$i}")
        ->type('button')
        ->data_id($i)
        ->class('delegated-btn');
}

echo div(
    div(...$buttons)->role('group')->aria_label('Delegated buttons'),
    output('Click a button.')->id('delegation-output')->aria_live('polite')
)
    ->id('delegation-container')
    ->class('delegation-container');
?>
<script>
const delegationContainer = document.getElementById('delegation-container');
const delegationOutput = document.getElementById('delegation-output');

delegationContainer.addEventListener('click', event => {
    const selectedButton = event.target.closest('.delegated-btn');

    if (selectedButton && delegationContainer.contains(selectedButton)) {
        delegationOutput.textContent = `Clicked button ${selectedButton.dataset.id}`;
    }
});
</script>
```

监听器直接挂在预期读取的元素上时应使用 `event.currentTarget`。`event.target` 可能是后代元素，因此适合事件委托。

## Shape 事件通信

编译后的 Shape 可以把可信展示数据放进属性，同时让 JavaScript 在外部处理事件：

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{button, div, output, p};

function ChildShape(string $message): string
{
    static $render;
    $render ??= Compile::shape(
        button(Slot::value('message'))
            ->type('button')
            ->data_role('child-button')
            ->data_message(Slot::value('message'))
    );

    return $render(['message' => $message]);
}

function ParentShape(string $child): string
{
    static $render;
    $render ??= Compile::shape(
        div(
            p('Parent shape'),
            Slot::raw('child'),
            output('Waiting for the child.')->id('parent-output')->aria_live('polite')
        )
    );

    return $render(['child' => $child]);
}

echo ParentShape(ChildShape('Send from the child'));
?>
<script>
const childButton = document.querySelector('[data-role="child-button"]');
const parentOutput = document.getElementById('parent-output');

if (childButton) {
    childButton.addEventListener('click', () => {
        parentOutput.textContent = `Received from child: ${childButton.dataset.message}`;
    });
}
</script>
```

这是 Shape 组合，而不是注册过的组件单元。消息会作为普通属性数据转义，不会把请求值插入 JavaScript 源码。完整流程见[编译渲染](/zh/guide/compiled)。

## 自定义浏览器事件

下面的图片使用自包含 data URI，因此示例不依赖任何占位图服务：

```php
<?php

use function Pure\HTML\{button, div, img, output};

echo div(
    img()
        ->src('data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==')
        ->alt('Embedded placeholder image')
        ->width(200)
        ->height(100)
        ->id('load-image'),
    button('Focus me')->type('button')->id('focus-button'),
    output('Waiting.')->id('custom-status')->aria_live('polite')
)->class('custom-events');
?>
<script>
const loadImage = document.getElementById('load-image');
const focusButton = document.getElementById('focus-button');
const customStatus = document.getElementById('custom-status');

function announceImageLoaded() {
    customStatus.textContent = 'Image loaded.';
}

function announceImageError() {
    customStatus.textContent = 'Image failed to load.';
}

loadImage.addEventListener('load', announceImageLoaded);
loadImage.addEventListener('error', announceImageError);
if (loadImage.complete) announceImageLoaded();

focusButton.addEventListener('focus', () => {
    focusButton.style.outline = '2px solid blue';
});

focusButton.addEventListener('blur', () => {
    focusButton.style.outline = '';
});
</script>
```

对于本地资源，可以使用 `/assets/placeholder.svg` 这样的稳定应用路径，并把文件放在页面同源目录下。

## 事件处理最佳实践

### 优先使用 `addEventListener`

先渲染语义化 HTML，再在同源 JavaScript 文件中附加行为。状态文本使用 `textContent`，请求数据放在文本或已转义的数据属性中，只在确实需要替换浏览器默认行为时调用 `preventDefault()`。

独立示例把 `<script>` 保留为内联块，便于复制运行。启用严格 CSP 的生产页面应把监听器代码移到外部 `.js` 文件，或用 nonce/hash 明确授权脚本。

### CSP 与内联属性

`->onclick(...)`、`->onsubmit(...)` 等方法会创建内联事件处理属性。未允许 `'unsafe-inline'` 的 CSP `script-src` 策略会阻止这些属性。在允许执行的外部脚本中通过 `addEventListener` 附加 JavaScript，不属于内联处理属性，因此应将其作为默认方式，也不要只为保留 `onclick` 而削弱 CSP。

## 下一步

- [HTMX 集成](/zh/guide/htmx)——把服务端渲染的片段与 HTMX 结合
- [组件](/zh/guide/components)——围绕 Shape 组合可复用单元
- [Props 与 Slot](/zh/guide/props)——绑定展示数据而不把它注入脚本
