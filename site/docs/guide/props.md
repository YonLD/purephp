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

div('Content')->class('container', 'mt-4')->id('main');
```

### Data and ARIA Attributes

Attribute names containing hyphens use underscores, because `-` is not valid
in a PHP method name:

```php
<?php

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

input()->type('checkbox')->checked(true);  // checked="checked"
input()->type('checkbox')->checked(false); // no checked attribute
```

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

$shape = Compile::shape(
    button('Save')->class(Slot::value('classList'))->disabled(Slot::value('disabled'))
);

$shape(['classList' => 'btn btn-primary', 'disabled' => null]);       // <button class="btn btn-primary">Save</button>
$shape(['classList' => 'btn btn-primary', 'disabled' => 'disabled']); // disabled="disabled"
```

## Slot Reference

| Slot | Value | Behavior |
| --- | --- | --- |
| `Slot::value($name)` | stringable; `null` only in an optional or attribute slot | position decides the semantics: child position escapes to text (`true` renders "1"; a required slot rejects `null`); attribute position follows `setAttr()` (`true` renders `name="name"`, `false`/`null` omit the attribute) |
| `Slot::raw($name)` | stringable, or an iterable of those | emitted verbatim, never escaped; an iterable is concatenated in order |
| `Slot::child($name, $shape)` | array | nested scope for `$shape` |
| `Slot::each($name, $shape)` | iterable of arrays | renders `$shape` per item |
| `Slot::if($name, $then, $else = null)` | truthy check | renders a branch; a missing key is false |

## Modifiers

```php
<?php

use Pure\Core\Slot;

Slot::value('subtitle')->required(false);   // missing key renders as empty
Slot::value('subtitle')->default('—');       // fallback for a missing key
```

- `required(false)` makes a slot optional; a missing key and an explicit `null`
  both render empty (an attribute is omitted instead).
- A required value or raw slot accepts neither a missing key nor an explicit
  `null`.
- `default($value)` provides a fallback for a missing key and makes the slot
  optional. The default is inlined into the compiled renderer, so it must be a
  value type: `null`, a scalar or an array of value types.
- `Slot::if()` rejects both modifiers with a `LogicException`: its condition is
  truthiness with a `false` fallback.

## Value Coercion and Escaping

Value and raw slots accept scalars and `Stringable` objects — including a `Raw`,
which needs no cast — and an optional slot also accepts `null`. Values are
converted to string before use; arrays and other objects raise an
`InvalidArgumentException` naming the full slot path. A raw slot goes one step
further and accepts an iterable of stringable values, concatenating them in
order.

- `Slot::value()` in child position escapes with
  `htmlspecialchars(..., double_encode: false)`, so entities you already escaped
  (`&copy;`) stay intact.
- `Slot::value()` in attribute position escapes with `double_encode: true`.
- `Slot::raw()` performs no escaping — only use it with trusted markup.
- Invalid UTF-8 is substituted with the replacement character instead of
  producing broken output.

## Missing Data

Required slots throw `Pure\Core\MissingSlotException` with the full path. The
message makes a typo visible: it suggests the closest provided key, or lists the
keys the scope did provide. An explicit `null` fails a required value or raw
slot with its own message (attribute slots keep omitting themselves):

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

Paths identify nested scopes: `card.title` for a child slot, `items[].title` for
a list item.

## Derived Props

A child component reads its props from the nested data under its slot name, so
derive them in the data layer before rendering:

```php
<?php

use Pure\Compile\Compile;
use Pure\Core\Slot;

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
