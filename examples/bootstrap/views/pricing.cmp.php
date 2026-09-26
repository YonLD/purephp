<?php declare(strict_types=1);


use Pure\Component\Binds;
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{body, div, head, html, link, meta, title};

use function Pure\Utils\renderHTML;

require_once __DIR__ . '/PageHeader.cmp.php';
require_once __DIR__ . '/PricingHeader.cmp.php';
require_once __DIR__ . '/CardDeck.cmp.php';
require_once __DIR__ . '/PageFooter.cmp.php';
require_once __DIR__ . '/../app/services/PricingService.php';

/**
 * The pricing page skeleton: everything but the four component blocks is
 * static. `pure compile` precompiles it into views/pricing.pure.php.
 */
function Pricing(): Call
{
    return component(__FUNCTION__);
}

register(Pricing(...), static fn () => (
    html(
        head(
            meta()->http_equiv('Content-Type')->content('text/html; charset=UTF-8'),
            meta()->name('viewport')->content('width=device-width, initial-scale=1, shrink-to-fit=no'),
            meta()->name('description')->content(''),
            meta()->name('author')->content(''),
            link()->rel('icon')->href('https://getbootstrap.com/docs/4.0/assets/img/favicons/favicon.ico'),
            title('Pricing example for Bootstrap'),
            link()->rel('canonical')->href('https://getbootstrap.com/docs/4.0/examples/pricing/'),
            link()->href('https://getbootstrap.com/docs/4.0/dist/css/bootstrap.min.css')->rel('stylesheet'),
            link()->href('./pricing.css')->rel('stylesheet')
        ),
        body(
            Slot::raw('header'),
            Slot::raw('pricing'),
            div(
                Slot::raw('deck'),
                Slot::raw('footer')
            )->class('container')
        )
    )->lang('en')
), prepare: #[Binds('header', 'pricing', 'deck', 'footer')] static fn (): array => pricingBindings());

/**
 * The rendered blocks of the pricing page: every component fetches its own
 * records from PricingService, so the page only decides which blocks exist.
 * The page function and the plain view controller share these bindings, so
 * both flavors render one page.
 *
 * Every block is a fluent component call; the plain view controller passes the
 * same bindings, and its loader renders the calls to strings before the view
 * loads.
 *
 * @return array{header: Call, pricing: Call, deck: Call, footer: Call}
 */
function pricingBindings(): array
{
    return [
        'header' => PageHeader(),
        'pricing' => PricingHeader(),
        'deck' => CardDeck(),
        'footer' => PageFooter(),
    ];
}

/**
 * The pricing page: the document skeleton comes from views/pricing.cmp.php
 * (precompiled with `pure compile`), the blocks come from the unit's own
 * prepare() hook. The document header is not part of the tree, so it is
 * prepended manually.
 */
function pricingPage(): string
{
    return renderHTML(component('Pricing'));
}
