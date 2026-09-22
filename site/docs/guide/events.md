# Events

**Prerequisites**: [Basic Usage](/guide/basic-usage); **On this page**: event attributes and browser-side handlers.

This guide explains how to handle events in PurePHP components.

::: tip Event attributes are part of the tag API
Event attributes such as `->onclick(...)` are part of the tag API and stay
valid; they are static attributes, so they are set the same way on shapes. For
pages, build shapes and compile them — see
[Compiled Rendering](/guide/compiled). Most examples below use the tag API,
which remains the immediate rendering path for snippets and debugging.
:::

## Basic Event Handling

PurePHP supports all standard HTML events through attribute methods. Event handlers are typically JavaScript functions passed as strings:

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

## Mouse Events

Handle various mouse interactions:

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

## Keyboard Events

Handle keyboard input:

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

## Form Events

Handle form interactions:

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
    event.preventDefault(); // Prevent actual form submission
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    document.getElementById('form-status').textContent =
        `Form submitted with: ${JSON.stringify(data)}`;

    console.log('Form data:', data);
}
</script>
```

## Event Delegation

Handle events efficiently using event delegation:

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

## Component Event Communication

Pass event handlers to child components as static event attributes; dynamic
values flow through slots:

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

The handler is compiled into the shape as a static attribute, so the handler
name cannot come from request data. See
[Compiled Rendering](/guide/compiled) for the full pipeline.

## Custom Event Attributes

Handle any HTML event attribute:

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

## Event Handler Best Practices

### 1. Inline vs External Handlers

```php
<?php

use function Pure\HTML\{div, button};

// Inline handler (good for simple actions)
$inlineButton = button('Inline Handler')
    ->onclick('alert("Hello from inline!")')
    ->class('btn');

// External handler (better for complex logic)
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
    // Complex logic here
    console.log('Complex action executed');
    // ... more code
}
</script>
```

### 2. Event Object Usage

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

## Next Steps

- [HTMX Integration](/guide/htmx) - Learn how to combine with HTMX
- [Components](/guide/components) - Components wrap shapes
- [Props and Slots](/guide/props) - Slot types and data binding
