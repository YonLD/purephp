<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2};

require_once __DIR__ . '/../app/services/FeaturesService.php';

/**
 * One page section: it fetches its own title and item records from the
 * service, renders every record with the component the page passes, and hands
 * the result to the template. The raw slot joins the list.
 *
 *     Section()
 *         ->section('columns')
 *         ->class('row g-4 py-5 row-cols-1 row-cols-lg-3')
 *         ->item(static fn (array $record): Call => IconColumn()->props($record));
 */
function Section(): Call
{
    return component(__FUNCTION__);
}

/**
 * One page section template: a heading and a grid of already rendered items.
 */
register(Section(...),
    factory: static fn () => div(
        h2(Slot::value('title'))->class('pb-2 border-bottom'),
        div(Slot::raw('contents'))->class(Slot::value('class'))
    )->class('container px-4 py-5'),
    prepare: static function (string $section, string $class, callable $item): array {
        $data = FeaturesService::section($section);

        return [
            'title' => $data['title'],
            'contents' => array_map(static fn (array $record): string => (string)$item($record), $data['items']),
            'class' => $class,
        ];
    }
);
