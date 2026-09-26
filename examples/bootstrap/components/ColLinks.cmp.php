<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{a, div, h5, li, ul};

/**
 * One footer link column:
 * `ColLinks()->title('Company')->links([['text' => 'Team', 'href' => '#']])`.
 */
function ColLinks(): Call
{
    return component(__FUNCTION__);
}

/**
 * One footer link column template.
 */
register(ColLinks(...), static fn () =>
    div(
        h5(Slot::value('title')),
        ul(
            Slot::each('links', li(a(Slot::value('text'))->class('text-muted')->href(Slot::value('href'))))
        )->class('list-unstyled text-small')
    )->class('col-6 col-md')
);
