# Events

**Prerequisites**: [Basic Usage](/guide/basic-usage); **On this page**: accessible browser events, event delegation and CSP-safe listener setup.

PurePHP renders HTML; browser JavaScript handles interaction. Emit semantic controls and accessible status regions from PHP, then attach behavior with `addEventListener` in JavaScript.

::: tip Prefer external listeners
`->onclick(...)`, `->oninput(...)` and similar tag methods are valid: they write inline `onclick` or `oninput` attributes. A Content Security Policy that omits `'unsafe-inline'` blocks those handlers. The examples therefore keep behavior out of element attributes and use `addEventListener` instead.
:::

## Basic Event Handling

Save this as `events.php` in a project that has already installed PurePHP, then run `php events.php`:

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

The input has a real `<label>`, the action is a semantic `<button type="button">`, and the `<output aria-live="polite">` announces updates without moving keyboard focus.

## Mouse Events

Use a button for an action and expose the result through a live region:

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

## Keyboard Events

Keep the visible label, describe the live output and listen for keyboard input:

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

## Form Events

Associate every control with a label and announce validation or submission state:

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

## Event Delegation

One listener can handle a group of related buttons. The buttons remain independently keyboard-operable:

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

Use `event.currentTarget` when the listener is attached to the element you actually intend to read. `event.target` can be a descendant and is useful for delegation.

## Shape Event Communication

A compiled shape can place trusted display data in attributes while JavaScript keeps the handler external:

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

This is shape composition rather than a registered component unit. The message is escaped as ordinary attribute data; no request value is inserted into JavaScript source. See [Compiled Rendering](/guide/compiled) for the full pipeline.

## Custom Browser Events

The image below uses a self-contained data URI, so the example has no placeholder service dependency:

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

For a local asset, use a stable application path such as `/assets/placeholder.svg` and keep the file under the same origin as the page.

## Event Handler Best Practices

### Prefer `addEventListener`

Render semantic HTML first, then attach behavior in a same-origin JavaScript file. Use `textContent` for status text, keep request data in text or escaped data attributes, and call `preventDefault()` only when the browser default must be replaced.

The standalone examples keep their `<script>` blocks inline so each file can be copied and run. A production page with a strict CSP should move the listener code to an external `.js` file or authorize the script with a nonce or hash.

### CSP and Inline Attributes

`->onclick(...)`, `->onsubmit(...)` and similar methods create inline event-handler attributes. CSP `script-src` policies without `'unsafe-inline'` block those attributes. JavaScript attached with `addEventListener` from an allowed external script is not an inline handler attribute, so prefer that default and do not weaken CSP merely to keep `onclick` working.

## Next Steps

- [HTMX Integration](/guide/htmx) - Combine server-rendered fragments with HTMX
- [Components](/guide/components) - Compose reusable units around shapes
- [Props and Slots](/guide/props) - Bind display data without injecting it into scripts
