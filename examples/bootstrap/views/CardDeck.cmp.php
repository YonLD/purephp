<?php declare(strict_types=1);
use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\div;

require_once __DIR__ . '/../components/Card.cmp.php';
require_once __DIR__ . '/../app/services/PricingService.php';

/**
 * The pricing card deck: it fetches the card records from the service and
 * renders the cards into its own template. `CardDeck()`.
 */
function CardDeck(): Call
{
    return component(__FUNCTION__);
}

/**
 * The card deck template: the cards are rendered by Card() and injected as raw
 * markup.
 */
register(CardDeck(...),
    factory: static fn () => div(Slot::raw('cards'))->class('card-deck mb-3 text-center'),
    prepare: static fn (): array => [
        'cards' => array_map(
            static fn (array $card): Call => Card()
                ->type($card['type'])
                ->price($card['price'])
                ->features($card['features'])
                ->text($card['text'])
                ->class($card['class']),
            PricingService::deck()
        ),
    ]
);
