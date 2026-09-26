<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, footer};

require_once __DIR__ . '/../components/ColLogo.cmp.php';
require_once __DIR__ . '/../components/ColLinks.cmp.php';
require_once __DIR__ . '/../app/services/PricingService.php';

/**
 * The page footer: it fetches the logo and the link columns from the service
 * and renders them into its own template. `PageFooter()`.
 */
function PageFooter(): Call
{
    return component(__FUNCTION__);
}

/**
 * The page footer template: the logo column and the link columns, rendered by
 * ColLogo() and ColLinks() and injected as raw markup.
 */
register(PageFooter(...),
    factory: static fn () => footer(
        div(
            Slot::raw('logo'),
            Slot::raw('columns')
        )->class('row'),
    )->class('pt-4 my-md-5 pt-md-5 border-top'),
    prepare: static function (): array {
        $data = PricingService::footer();

        return [
            'logo' => ColLogo()->props($data['logo']),
            'columns' => array_map(
                static fn (array $column): Call => ColLinks()->title($column['title'])->links($column['links']),
                $data['columns']
            ),
        ];
    }
);
