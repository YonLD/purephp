<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\SVG\{svg, svgUse};

/**
 * An icon that references the SVG symbol sheet:
 * `Icon()->href('#home')` renders `<svg class="bi" ...><use href="#home" /></svg>`.
 */
function Icon(): Call
{
    return component(__FUNCTION__);
}

/**
 * The icon template: a `<use>` reference into the SVG symbol sheet. The class
 * and the size come from the data — with defaults, so the common call only
 * passes the href.
 */
register(Icon(...), static fn () =>
    svg(svgUse()->href(Slot::value('href')))
        ->class(Slot::value('class')->default('bi'))
        ->width(Slot::value('width')->default('1em'))
        ->height(Slot::value('height')->default('1em'))
);
