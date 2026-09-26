<?php declare(strict_types=1);


use Pure\Component\Binds;
use Pure\Component\Call;
use Pure\Core\Raw;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{body, h1, head, html, link, main, meta, title};

use function Pure\Utils\renderHTML;

require_once __DIR__ . '/../components/IconSheet.cmp.php';
require_once __DIR__ . '/../components/IconColumn.cmp.php';
require_once __DIR__ . '/../components/HangingIcon.cmp.php';
require_once __DIR__ . '/../components/CustomCard.cmp.php';
require_once __DIR__ . '/../components/CellIcon.cmp.php';
require_once __DIR__ . '/../components/MainFeature.cmp.php';
require_once __DIR__ . '/../components/FeatureTitle.cmp.php';
require_once __DIR__ . '/Section.cmp.php';
require_once __DIR__ . '/../components/Divider.cmp.php';
require_once __DIR__ . '/FeatureSection.cmp.php';
require_once __DIR__ . '/../app/services/FeaturesService.php';

/**
 * The features page call function: props flow through the unit's prepare()
 * hook, so a call is just component('Features') at the page functions below.
 */
function Features(): Call
{
    return component(__FUNCTION__);
}

register(Features(...), static function () {
    /**
     * The features page skeleton: the head and the SVG symbol sheet are static,
     * the body is composed from raw slots populated by featuresBindings().
     * `pure compile` precompiles it into views/features.pure.php.
     */
    $site = 'https://getbootstrap.com/docs/5.2';
    $favicons = $site . '/assets/img/favicons';

    return (
        html(
            head(
                meta()->charset('utf-8'),
                meta()->name('viewport')->content('width=device-width, initial-scale=1'),
                meta()->name('description')->content('pure demo'),
                meta()->name('author')->content('Mark Otto, Jacob Thornton, and Bootstrap contributors'),
                meta()->name('generator')->content('Hugo 0.104.2'),
                meta()->name('theme-color')->content('#712cf9'),
                title(Slot::value('title')),
                link()->rel('canonical')->href($site . '/examples/features/'),
                link()->rel('stylesheet')->crossorigin('anonymous')->href($site . '/dist/css/bootstrap.min.css')->integrity('sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65'),
                link()->rel('apple-touch-icon')->sizes('180x180')->href($favicons . '/apple-touch-icon.png'),
                link()->rel('icon')->type('image/png')->sizes('32x32')->href($favicons . '/favicon-32x32.png'),
                link()->rel('icon')->type('image/png')->sizes('16x16')->href($favicons . '/favicon-16x16.png'),
                link()->rel('manifest')->href($favicons . '/manifest.json'),
                link()->rel('mask-icon')->color('#712cf9')->href($favicons . '/safari-pinned-tab.svg'),
                link()->rel('icon')->href($favicons . '/favicon.ico'),
                link()->rel('stylesheet')->href($site . '/examples/features/features.css'),
                link()->rel('stylesheet')->href('./style.css')
            ),
            body(
                Raw::of((string)IconSheet()),
                main(
                    h1('Features examples')->class('visually-hidden'),
                    Slot::raw('columns'),
                    Raw::of((string)Divider()),
                    Slot::raw('hanging'),
                    Raw::of((string)Divider()),
                    Slot::raw('cards'),
                    Raw::of((string)Divider()),
                    Slot::raw('grid'),
                    Raw::of((string)Divider()),
                    Slot::raw('features')
                )
            )
        )
    );
}, prepare: #[Binds('title', 'columns', 'hanging', 'cards', 'grid', 'features')] static fn (): array => featuresBindings());

/**
 * The rendered blocks of the features page: every section component fetches
 * its own records from FeaturesService, so the page only decides which blocks
 * the skeleton has. The page function and the plain view controller share
 * these bindings, so both flavors render one page.
 *
 * Every block is a fluent component call; the plain view controller passes the
 * same bindings, and its loader renders the calls to strings before the view
 * loads.
 *
 * @return array{title: string, columns: Call, hanging: Call, cards: Call, grid: Call, features: Call}
 */
function featuresBindings(): array
{
    return [
        'title' => FeaturesService::pageTitle(),
        'columns' => Section()
            ->section('columns')
            ->class('row g-4 py-5 row-cols-1 row-cols-lg-3')
            ->item(static fn (array $record): Call => IconColumn()->props($record)),
        'hanging' => Section()
            ->section('hanging')
            ->class('row g-4 py-5 row-cols-1 row-cols-lg-3')
            ->item(static fn (array $record): Call => HangingIcon()->props($record)),
        'cards' => Section()
            ->section('cards')
            ->class('row row-cols-1 row-cols-lg-3 align-items-stretch g-4 py-5')
            ->item(static fn (array $record): Call => CustomCard()->props($record)),
        'grid' => Section()
            ->section('grid')
            ->class('row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 py-5')
            ->item(static fn (array $record): Call => CellIcon()->props($record)),
        'features' => FeatureSection(),
    ];
}

/**
 * The features page: the document skeleton is the registered template
 * (precompiled with `pure compile`), the blocks come from the unit's own
 * prepare() hook. The document header is not part of the tree, so it is
 * prepended manually.
 */
function featuresPage(): string
{
    return renderHTML(component('Features'));
}
