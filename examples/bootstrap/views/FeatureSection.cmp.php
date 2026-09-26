<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2};

require_once __DIR__ . '/../components/MainFeature.cmp.php';
require_once __DIR__ . '/../components/FeatureTitle.cmp.php';
require_once __DIR__ . '/../app/services/FeaturesService.php';

/**
 * The "features with title" section: it fetches the heading, the main column
 * and the feature records from the service and renders them into its own
 * template. `FeatureSection()`.
 */
function FeatureSection(): Call
{
    return component(__FUNCTION__);
}

/**
 * The "features with title" section template: the main column and the feature
 * items are rendered by MainFeature() and FeatureTitle() and injected as raw
 * markup.
 */
register(FeatureSection(...),
    factory: static fn () => div(
        h2(Slot::value('title'))->class('pb-2 border-bottom'),
        div(
            Slot::raw('main'),
            div(
                div(Slot::raw('features'))->class('row row-cols-1 row-cols-sm-2 g-4')
            )->class('col')
        )->class('row row-cols-1 row-cols-md-2 align-items-md-center g-5 py-5')
    )->class('container px-4 py-5'),
    prepare: static function (): array {
        $data = FeaturesService::featureSection();

        return [
            'title' => $data['title'],
            'main' => MainFeature()->props($data['main']),
            'features' => array_map(
                static fn (array $feature): Call => FeatureTitle()->props($feature),
                $data['features']
            ),
        ];
    }
);
