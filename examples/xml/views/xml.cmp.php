<?php declare(strict_types=1);

use Pure\Compile\Compile;
use Pure\Compile\Shape;
use Pure\Compile\Template;
use Pure\Component\Call;

use Pure\Core\Slot;
use Pure\Core\XML;

use function Pure\Component\{component, register};

use function Pure\Utils\renderXML;

/**
 * Address shape: fields follow the data key order; `city` is optional and
 * rendered only when the record provides it.
 */
#[Template]
function AddressShape(): Shape
{
    static $shape;

    return $shape ??= Compile::shape(
        XML::address(
            XML::street(Slot::value('street')),
            Slot::if('city', XML::city(Slot::value('city'))),
            XML::state(Slot::value('state')),
            XML::zip(Slot::value('zip'))
        )
    );
}

/**
 * The customer list as a shape file: `pure compile` precompiles it into
 * views/xml.pure.php, and app/controllers render the same shape without
 * precompiling.
 */
#[Template]
function XmlPageShape(): Shape
{
    static $shape;

    return $shape ??= Compile::shape(
        XML::customers(
            XML::customer(
                XML::name('Charter Group'),
                Slot::each('addresses', AddressShape())
            )->id('55000')
        )
    );
}

/**
 * The XML page unit: it serves the shape beside it, so a controller can call
 * `component('Xml')->addresses($records)`.
 */
function Xml(): Call
{
    return component(__FUNCTION__);
}

register(Xml(...), static fn () => XmlPageShape());

/**
 * The xml page: the document comes from views/xml.cmp.php (precompiled with
 * `pure compile`). The XML declaration is not part of the tree, so renderXML()
 * prepends it.
 *
 * @param array<string, mixed> $data The page data from the controller.
 */
function xmlPage(array $data): string
{
    return renderXML(component('Xml')->addresses($data['addresses']));
}
