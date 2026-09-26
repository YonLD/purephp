<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Component\Prop;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h3, img, li, small, ul};
use function Pure\SVG\{svg, svgUse};

/**
 * One "custom cards" item: the cover image is the card background, the icon is
 * the avatar. `CustomCard()->title(...)->icon(...)->location(...)->date(...)->bgImg(...)`.
 */
function CustomCard(): Call
{
    return component(__FUNCTION__);
}

/**
 * One "custom cards" item template.
 */
register(CustomCard(...),
    factory: static fn () => div(
        div(
            div(
                h3(Slot::value('title'))->class('pt-5 mt-5 mb-4 display-6 lh-1 fw-bold'),
                ul(
                    li(
                        img()->src(Slot::value('icon'))->alt('Bootstrap')->width('32')->height('32')->class('rounded-circle border border-white')
                    )->class('me-auto'),
                    li(
                        svg(svgUse()->href('#geo-fill'))->class('bi me-2')->width('1em')->height('1em'),
                        small(Slot::value('location'))
                    )->class('d-flex align-items-center me-3'),
                    li(
                        svg(svgUse()->href('#calendar3'))->class('bi me-2')->width('1em')->height('1em'),
                        small(Slot::value('date'))
                    )->class('d-flex align-items-center'),
                )->class('d-flex list-unstyled mt-auto')
            )->class('d-flex flex-column h-100 p-5 pb-3 text-white text-shadow-1')
        )->class('card card-cover h-100 overflow-hidden text-bg-dark rounded-4 shadow-lg')->style(Slot::value('style'))
    )->class('col'),
    prepare: static fn (string $title, string $icon, string $location, string $date, #[Prop(slot: 'style')] string $bgImg): array => [
        'title' => $title,
        'icon' => $icon,
        'location' => $location,
        'date' => $date,
        'style' => "background-image: url('{$bgImg}');",
    ]
);
