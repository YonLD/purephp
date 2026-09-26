<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{a, div, h3, p};
use function Pure\SVG\{svg, svgUse};

require_once __DIR__ . '/Icon.cmp.php';

/**
 * One "columns with icons" item: `IconColumn()->icon('collection')->title(...)->content(...)->link(...)->linkText(...)`.
 */
function IconColumn(): Call
{
    return component(__FUNCTION__);
}

/**
 * One "columns with icons" item template.
 */
register(IconColumn(...),
    factory: static fn () => div(
        div(
            Slot::raw('icon')
        )->class('feature-icon d-inline-flex align-items-center justify-content-center text-bg-primary bg-gradient fs-2 mb-3'),
        h3(Slot::value('title'))->class('fs-2'),
        p(Slot::value('content')),
        a(
            Slot::value('linkText'),
            svg(svgUse()->href('#chevron-right'))->class('bi')->width('1em')->height('1em')
        )->href(Slot::value('link'))->class('icon-link d-inline-flex align-items-center')
    )->class('feature col'),
    prepare: static fn (string $icon, string $title, string $content, string $link, string $linkText): array => [
        'icon' => Icon()->href('#' . $icon),
        'title' => $title,
        'content' => $content,
        'link' => $link,
        'linkText' => $linkText,
    ]
);
