<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{a, div, h3, p};

require_once __DIR__ . '/Icon.cmp.php';

/**
 * One "hanging icons" item: `HangingIcon()->icon('bootstrap')->title(...)->content(...)->link(...)->linkText(...)`.
 */
function HangingIcon(): Call
{
    return component(__FUNCTION__);
}

/**
 * One "hanging icons" item template.
 */
register(HangingIcon(...),
    factory: static fn () => div(
        div(
            Slot::raw('icon')
        )->class('icon-square text-bg-light d-inline-flex align-items-center justify-content-center fs-4 flex-shrink-0 me-3'),
        div(
            h3(Slot::value('title'))->class('fs-2'),
            p(Slot::value('content')),
            a(Slot::value('linkText'))->href(Slot::value('link'))->class('btn btn-primary')
        )
    )->class('col d-flex align-items-start'),
    prepare: static fn (string $icon, string $title, string $content, string $link, string $linkText): array => [
        'icon' => Icon()->href('#' . $icon),
        'title' => $title,
        'content' => $content,
        'link' => $link,
        'linkText' => $linkText,
    ]
);
