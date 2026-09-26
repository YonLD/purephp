<?php declare(strict_types=1);

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h3, p};

require_once __DIR__ . '/Icon.cmp.php';

/**
 * One "icon grid" item: `CellIcon()->icon('speedometer2')->title(...)->content(...)`.
 */
function CellIcon(): Call
{
    return component(__FUNCTION__);
}

/**
 * One "icon grid" item template.
 */
register(CellIcon(...),
    factory: static fn () => div(
        Slot::raw('icon'),
        div(
            h3(Slot::value('title'))->class('fw-bold mb-0 fs-4'),
            p(Slot::value('content'))
        )
    )->class('col d-flex align-items-start'),
    prepare: static fn (string $icon, string $title, string $content): array => [
        'icon' => Icon()->href('#' . $icon)->class('bi text-muted flex-shrink-0 me-3')->width('1.75em')->height('1.75em'),
        'title' => $title,
        'content' => $content,
    ]
);
