# Props and Slots

**Prerequisites**: [Core Concepts](/guide/concepts); **On this page**: slot types, modifiers and the data-binding reference.

In PurePHP, "props" come in two forms:

- **Static props** — values known while the component is built (function
  arguments, literal attributes).
- **Dynamic props** — values bound at render time: `Slot` placeholders.

This page is the data-binding reference; see
[Compiled Rendering](/guide/compiled) for the rendering pipeline itself.

## Static Props

### HTML Attributes

Attributes are set with method chaining and are stored in the shape as
literals:

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

`className()` is an alias of `class()`, and many attributes can be passed to
`class()`:

```php
<?php

use function Pure\HTML\div;

div('Content')->class('container', 'mt-4')->id('main');
```

### Data and ARIA Attributes

Attribute names containing hyphens use underscores, because `-` is not valid
in a PHP method name:

```php
<?php

use function Pure\HTML\div;

div('Content')
    ->data_id('123')      // data-id="123"
    ->data_type('card')   // data-type="card"
    ->aria_label('Card'); // aria-label="Card"
```

### Boolean Attributes

A `true` value renders the attribute with its own name as value; `false` and
`null` omit it:

```php
<?php

use function Pure\HTML\input;

input()->type('checkbox')->checked(true);  // checked="checked"
input()->type('checkbox')->checked(false); // no checked attribute
```

A later `false` or `null` setter is ignored; it does not remove an attribute that
an earlier setter already added. For example,
`input()->checked(true)->checked(false)` still renders `checked="checked"`.
Build the value in one setter call, or start a fresh tag, when a later value
must clear the attribute.

`Slot::value()` follows the same rules at render time, so static and dynamic
attributes cannot drift apart: a bound `false` omits the attribute and a bound
`true` renders `checked="checked"`.

## Dynamic Props

Dynamic attribute values use `Slot::value()`. The argument is the data key, not
the attribute name — the attribute name comes from the setter, so
`->class(Slot::value('classList'))` binds `classList` from the data and writes it
into `class`. A `null` value omits the attribute at render time (a bound `false`
behaves the same), which is also how conditional attributes work:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\button;

$shape = Compile::shape(
    button('Save')->class(Slot::value('classList'))->disabled(Slot::value('disabled'))
);

$shape(['classList' => 'btn btn-primary', 'disabled' => null]);       // <button class="btn btn-primary">Save</button>
$shape(['classList' => 'btn btn-primary', 'disabled' => 'disabled']); // disabled="disabled"
```

## Slot Reference

| Slot | Value | Behavior |
| --- | --- | --- |
| `Slot::value($name)` | stringable; `null` is valid only when the effective value is optional or the slot is in attribute position | position decides the semantics: child position escapes to text (`true` renders "1"; a required value slot in child/text position rejects `null`); attribute position follows `setAttr()` (`true` renders `name="name"`, `false`/`null` omit the attribute) |
| `Slot::raw($name)` | stringable, or an iterable of those; `null` is valid only for an optional slot | emitted verbatim, never escaped; an iterable is concatenated in order; a required raw slot rejects `null` |
| `Slot::child($name, $shape)` | array | nested scope for `$shape`; the value must still be an array when the slot is optional |
| `Slot::each($name, $shape)` | iterable of arrays | renders `$shape` per item; the value must still be iterable when the slot is optional |
| `Slot::if($name, $then, $else = null)` | truthy check | renders a branch; a missing key is false |

Choose a list slot by whether the markup already exists: `Slot::raw()`
concatenates markup that is already rendered, while `Slot::each()` renders one
item shape per data item.

## Modifiers

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\div;

$userShape = Compile::shape(div(Slot::value('name')));
$itemShape = Compile::shape(div(Slot::value('label')));
Slot::value('subtitle')->required(false);   // missing key renders as empty
Slot::value('subtitle')->default('—');       // fallback for a missing key
Slot::child('user', $userShape)->default([]);
Slot::each('items', $itemShape)->default([]);
```

- `required(false)` makes a value or raw slot optional; a missing key and an
  explicit `null` use the empty/fallback value. A required value or raw slot
  accepts neither a missing key nor an explicit `null` in child/text position.
  In attribute position, `Slot::value()` follows `setAttr()` and omits a
  `null`; a raw slot cannot be used as an attribute value.
- For `child` and `each`, the modifier controls the missing-key fallback, not
  the container type. The effective value must still be an array for `child`
  and an iterable for `each`; `default(null)` therefore fails when the branch
  is rendered. Use `default([])` (or another valid container) when the branch
  should be empty. Static-analysis docblocks may still show `|null` for an
  optional container, but that describes missing data; it does not change the
  runtime type check.
- `default($value)` provides a fallback for a missing key and makes the slot
  optional. The default is inlined into the compiled renderer, so it must be a
  value type: `null`, a scalar or an array of value types.
- `Slot::if()` rejects both modifiers with a `LogicException`: its condition is
  truthiness with a `false` fallback.

## Value Coercion and Escaping

Value and raw slots accept scalars and `Stringable` objects — including a `Raw`,
which needs no cast — and an optional value/raw slot also accepts `null` when
its effective value is used. Values are converted to string before use; arrays
and other objects raise an `InvalidArgumentException` naming the full slot
path. A raw slot goes one step further and accepts an iterable of stringable
values, concatenating them in order; a non-stringable element reports its
offset, for example `slot 'items[2]' must be stringable`. `child` and `each`
values are type-checked
even when the slot is optional: a child needs an array and an each needs an
iterable.

- `Slot::value()` in child position escapes with
  `htmlspecialchars(..., double_encode: false)`, so entities you already escaped
  (`&copy;`) stay intact.
- `Slot::value()` in attribute position escapes with `double_encode: true`.
- `Slot::raw()` performs no escaping — only use it with trusted markup.
- Invalid UTF-8 is substituted with the replacement character instead of
  producing broken output.

Escaping protects text and attribute syntax; it is not a complete URL or script
policy. Validate `href`/`src` schemes, do not bind untrusted event-handler or
style attributes, restrict dynamic tag names, and use a suitable Content
Security Policy for pages that contain `Raw` markup.

## Missing Data

Required slots throw `Pure\Core\MissingSlotException` with the full path. The
message makes a typo visible: it suggests the closest provided key, or lists the
keys the scope did provide. An explicit `null` fails a required value or raw
slot with its own message (attribute slots keep omitting themselves):

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

Paths identify nested scopes: `card.title` for a child slot, `items[].title` for
a list item.

## Derived Props

A child component reads its props from the nested data under its slot name, so
derive them in the data layer before rendering:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, span};

$badge = Compile::shape(span(Slot::value('label'))->class('badge'));

$shape = Compile::shape(div(Slot::child('user', $badge)));

$shape(['user' => ['label' => 'ADA']]); // <div><span class="badge">ADA</span></div>
```

A nested shape can be a bare tag tree — `Slot::child('user', span(Slot::value('label')))`
works too; `Compile::shape()` is only needed when the nested tree is built and
memoized separately.

`Slot::each()` reads its items the same way: every item
is already the item scope, so a controller turns a list of rows into a list of
prop arrays before handing it to the shape.

## Component Props Contract

Because a shape is data-free, a component's data contract lives in its slots.
Document it next to the component and keep the bindings array in one place; a
missing required key will fail loudly with the full path at render time.

## Next Steps

- [Components](/guide/components) - Components wrap shapes
- [Compiled Rendering](/guide/compiled) - Lists, conditionals, caching and limitations
- [Artifacts & Deployment](/guide/artifacts) - `pure compile` artifacts and production deployment
- [Core Concepts](/guide/concepts) - Shapes, scopes and compiling
