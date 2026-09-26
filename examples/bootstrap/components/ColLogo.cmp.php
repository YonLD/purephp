<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, img, small};

/**
 * The footer logo column: `ColLogo()->src(...)->width('24')->height('24')->text(...)`.
 */
function ColLogo(): Call
{
    return component(__FUNCTION__);
}

/**
 * The footer logo column template.
 */
register(ColLogo(...), static fn () =>
    div(
        img()->class('mb-2')->src(Slot::value('src'))->width(Slot::value('width'))->height(Slot::value('height')),
        small(Slot::value('text'))->class('d-block mb-3 text-muted')
    )->class('col-12 col-md')
);
