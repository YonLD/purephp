<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Component\Prop;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{button, div, h1, h4, li, small, ul};

/**
 * One pricing card, called fluently:
 *
 *     Card()
 *         ->type('Free')
 *         ->price('0')
 *         ->features(['10 users included', '2 GB of storage'])
 *         ->text('Sign up for free')
 *         ->class('btn btn-lg btn-block btn-outline-primary');
 */
function Card(): Call
{
    return component(__FUNCTION__);
}

/**
 * One pricing card template.
 */
register(Card(...),
    factory: static fn () => div(
        div(
            h4(Slot::value('type'))->class('my-0 font-weight-normal')
        )->class('card-header'),
        div(
            h1('$', Slot::value('price'), ' ', small('/ mo')->class('text-muted'))->class('card-title pricing-card-title'),
            ul(Slot::each('features', li(Slot::value('value'))))->class('list-unstyled mt-3 mb-4'),
            button(Slot::value('text'))->type('button')->class(Slot::value('class'))
        )->class('card-body')
    )->class('card mb-4 box-shadow'),
    prepare: static fn (string $type, string $price, #[Prop(item: 'value')] array $features, string $text, string $class): array => [
        'type' => $type,
        'price' => $price,
        'features' => array_map(static fn (string $feature): array => ['value' => $feature], $features),
        'text' => $text,
        'class' => $class,
    ]
);
