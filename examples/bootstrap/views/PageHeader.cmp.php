<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h5, nav};

require_once __DIR__ . '/../components/NavLink.cmp.php';
require_once __DIR__ . '/../app/services/PricingService.php';

/**
 * The page header: it fetches the company name, the nav links and the sign-up
 * link from the service and renders them into its own template.
 * `PageHeader()`.
 */
function PageHeader(): Call
{
    return component(__FUNCTION__);
}

/**
 * The page header template: the company name, the nav links and the sign-up
 * link. The links are rendered by NavLink() and injected as raw markup.
 */
register(PageHeader(...),
    factory: static fn () => div(
        h5(Slot::value('company'))->class('my-0 mr-md-auto font-weight-normal'),
        nav(Slot::raw('navs'))->class('my-2 my-md-0 mr-md-3'),
        Slot::raw('signUp')
    )->class('d-flex flex-column flex-md-row align-items-center p-3 px-md-4 mb-3 bg-white border-bottom box-shadow'),
    prepare: static function (): array {
        $data = PricingService::header();

        return [
            'company' => $data['company'],
            'navs' => array_map(static fn (array $nav): Call => NavLink()->props($nav), $data['navs']),
            'signUp' => NavLink()->props($data['signUp']),
        ];
    }
);
