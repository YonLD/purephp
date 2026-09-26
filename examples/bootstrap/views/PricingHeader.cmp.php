<?php declare(strict_types=1);
use Pure\Component\Binds;
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h1, p};

require_once __DIR__ . '/../app/services/PricingService.php';

/**
 * The pricing page heading: it fetches the title and the description from the
 * service. `PricingHeader()`.
 */
function PricingHeader(): Call
{
    return component(__FUNCTION__);
}

/**
 * The pricing page heading template.
 */
register(PricingHeader(...),
    factory: static fn () => div(
        h1(Slot::value('title'))->class('display-4'),
        p(Slot::value('desc'))->class('lead')
    )->class('pricing-header px-3 py-3 pt-md-5 pb-md-4 mx-auto text-center'),
    prepare: #[Binds('title', 'desc')] static fn (): array => PricingService::pricing()
);
