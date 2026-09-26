<?php declare(strict_types=1);

use Pure\Compile\Compile;
use Pure\Compile\Shape;
use Pure\Compile\Template;
use Pure\Component\Call;

use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button, div, h1, span};

use function Pure\Utils\renderHTML;

/**
 * The counter page as a shape file: `pure compile` precompiles it into
 * views/counter.pure.php, and app/controllers render the same shape without
 * precompiling.
 *
 * `initial` is a slot so the controller can provide a server-random start.
 */
#[Template]
function CounterPageShape(): Shape
{
    static $shape;

    return $shape ??= Compile::shape(
        div(
            h1('JavaScript Counter App'),
            div(
                button('+')->id('add'),
                span(Slot::value('initial'))->id('output'),
                button('-')->id('subtract')
            )->class('counter-container')
        )
    );
}

/**
 * The counter page unit: it serves the shape beside it, so a controller can
 * call `component('Counter')->initial($count)`.
 */
function Counter(): Call
{
    return component(__FUNCTION__);
}

register(Counter(...), static fn () => CounterPageShape());

/**
 * The counter page: the document skeleton comes from views/counter.cmp.php
 * (precompiled with `pure compile`). The document header is not part of the
 * tree, so it is prepended manually.
 *
 * @param array<string, mixed> $data The page data from the controller.
 */
function counterPage(array $data): string
{
    return renderHTML(component('Counter')->initial($data['initial']));
}
