<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\a;

/**
 * One navigation link with its own class list: `NavLink()->text('Home')->href('/')`.
 */
function NavLink(): Call
{
    return component(__FUNCTION__);
}

/**
 * One navigation link; the class comes from the data — with a default, so the
 * same template serves the header links and the sign-up button.
 */
register(NavLink(...), static fn () =>
    a(Slot::value('text'))->class(Slot::value('class')->default(''))->href(Slot::value('href'))
);
