<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pure\Compile\Compile;
use Pure\Core\MissingSlotException;
use Pure\Core\Slot;

use function Pure\HTML\div;
use function Pure\HTML\em;
use function Pure\HTML\li;
use function Pure\HTML\span;
use function Pure\HTML\table;
use function Pure\HTML\tr;
use function Pure\HTML\ul;

/**
 * Behaviour of the conditional and list slots.
 */
class SlotTest extends TestCase
{
    public function testIfRendersThenOrElseBranch(): void
    {
        $shape = Compile::shape(div(
            Slot::if('admin', span('admin'), span('guest'))
        ));

        $this->assertSame('<div><span>admin</span></div>', $shape(['admin' => true]));
        $this->assertSame('<div><span>guest</span></div>', $shape(['admin' => false]));
        $this->assertSame('<div><span>guest</span></div>', $shape([]));
    }

    public function testIfWithoutElseRendersNothingWhenFalsy(): void
    {
        $shape = Compile::shape(div('a', Slot::if('show', span('!')), 'b'));

        $this->assertSame('<div>ab</div>', $shape([]));
        $this->assertSame('<div>a<span>!</span>b</div>', $shape(['show' => 1]));
    }

    public function testIfBranchesShareTheCurrentScope(): void
    {
        $shape = Compile::shape(div(
            Slot::if('admin', span(Slot::value('name')))
        ));

        $this->assertSame('<div><span>Tom</span></div>', $shape(['admin' => true, 'name' => 'Tom']));
    }

    public function testIfInsideEachUsesItemScope(): void
    {
        $item = Compile::shape(li(Slot::value('name'), Slot::if('admin', span('(a)'))));
        $shape = Compile::shape(ul(Slot::each('items', $item)));

        $this->assertSame(
            '<ul><li>Tom<span>(a)</span></li><li>Ann</li></ul>',
            $shape(['items' => [['name' => 'Tom', 'admin' => true], ['name' => 'Ann']]])
        );
    }

    public function testNestedSlotsAcceptBareTagTrees(): void
    {
        $bare = Compile::shape(div(
            Slot::child('box', span(Slot::value('label'))),
            Slot::each('items', li(Slot::value('value'))),
            Slot::if('flag', em('on'), em('off')),
        ));

        $wrapped = Compile::shape(div(
            Slot::child('box', Compile::shape(span(Slot::value('label')))),
            Slot::each('items', Compile::shape(li(Slot::value('value')))),
            Slot::if('flag', Compile::shape(em('on')), Compile::shape(em('off'))),
        ));

        $data = [
            'box' => ['label' => 'boxed'],
            'items' => [['value' => 'one'], ['value' => 'two']],
            'flag' => true,
        ];

        $expected = '<div><span>boxed</span><li>one</li><li>two</li><em>on</em></div>';

        $this->assertSame($expected, $bare($data));
        $this->assertSame($expected, $wrapped($data));
        $this->assertSame($bare->id(), $wrapped->id());
    }

    public function testIfModifiersAreRejected(): void
    {
        $slot = Slot::if('show', span('x'));

        try {
            $slot->default(true);
            $this->fail('Expected LogicException to be thrown.');
        } catch (LogicException $e) {
            $this->assertStringContainsString("slot 'show' is a condition slot", $e->getMessage());
        }

        $this->expectException(LogicException::class);

        $slot->required(false);
    }

    public function testDefaultRejectsNonValueTypes(): void
    {
        foreach ([static fn (): string => 'x', ['nested' => new stdClass()], new stdClass()] as $bad) {
            try {
                Slot::value('value')->default($bad);
                $this->fail('Expected InvalidArgumentException to be thrown.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('default must be null, a scalar or an array of value types', $e->getMessage());
            }
        }
    }

    public function testDefaultAcceptsAValueTypeArray(): void
    {
        $item = Compile::shape(li(Slot::value('v')));
        $shape = Compile::shape(ul(Slot::each('items', $item)->default([])));

        $this->assertSame('<ul></ul>', $shape([]));
    }

    public function testEachSlotBindsAScalarItemToTheOneKeyItsShapeRenders(): void
    {
        $shape = Compile::shape(ul(Slot::each('xs', li(Slot::value('value')))));

        $this->assertSame('<ul><li>a</li><li>b</li></ul>', $shape(['xs' => ['a', 'b']]));
        // An array item is a scope of its own, so both forms render together.
        $this->assertSame(
            '<ul><li>a</li><li>b</li></ul>',
            $shape(['xs' => [['value' => 'a'], 'b']])
        );
        $this->assertSame('<ul><li>1</li><li>2</li></ul>', $shape(['xs' => [1, 2]]));
    }

    public function testEachSlotBindsAScalarItemToAnAttributeSlotToo(): void
    {
        $shape = Compile::shape(ul(Slot::each('xs', li(Slot::value('value'))->class(Slot::value('value')))));

        $this->assertSame(
            '<ul><li class="a">a</li><li class="b">b</li></ul>',
            $shape(['xs' => ['a', 'b']])
        );
    }

    public function testEachSlotBindsAScalarItemThroughNestedScopes(): void
    {
        $shape = Compile::shape(table(tr(Slot::each('cells', li(Slot::value('value'))))));

        $this->assertSame(
            '<table><tr><li>a</li><li>b</li></tr></table>',
            $shape(['cells' => ['a', 'b']])
        );
    }

    public function testANullEachItemReportsTheSlotItWasBoundTo(): void
    {
        $shape = Compile::shape(ul(Slot::each('xs', li(Slot::value('value')))));

        $this->expectException(MissingSlotException::class);
        $this->expectExceptionMessage("slot 'xs[].value' is required but was null.");

        $shape(['xs' => [null]]);
    }

    public function testEachSlotRejectsAScalarItemWhenItsShapeReadsSeveralKeys(): void
    {
        $shape = Compile::shape(ul(Slot::each('xs', li(Slot::value('a'), Slot::value('b')))));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "slot 'xs[]' must be an array, string given. The item shape of this slot reads 'a' and 'b', so each item must be an array."
        );

        $shape(['xs' => ['x']]);
    }

    public function testEachSlotRejectsAScalarItemWhenItsShapeReadsANestedScope(): void
    {
        $box = Compile::shape(div(Slot::child('card', span(Slot::value('name')))));
        $shape = Compile::shape(ul(Slot::each('xs', $box)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "slot 'xs[]' must be an array, string given. The item shape of this slot reads 'card', so each item must be an array."
        );

        $shape(['xs' => ['x']]);
    }

    public function testEachSlotRejectsAScalarItemWhenItsShapeReadsNoSlots(): void
    {
        $shape = Compile::shape(ul(Slot::each('xs', li('x'))));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "slot 'xs[]' must be an array, string given. The item shape of this slot reads no slots, so each item must be an empty array."
        );

        $shape(['xs' => ['x']]);
    }

    public function testEachSlotStillNeedsAnArrayItemForItsOwnKeys(): void
    {
        $shape = Compile::shape(ul(Slot::each('xs', li(Slot::value('value')))));

        $this->expectException(MissingSlotException::class);
        $this->expectExceptionMessage("slot 'xs[].value' is required but was not provided; provided keys: 'other'.");

        $shape(['xs' => [['other' => 'a']]]);
    }

    public function testEachSlotBindsAScalarItemToARawSlot(): void
    {
        $shape = Compile::shape(ul(Slot::each('xs', li(Slot::raw('html')))));

        $this->assertSame('<ul><li><b>a</b></li><li><i>b</i></li></ul>', $shape(['xs' => ['<b>a</b>', '<i>b</i>']]));
    }

    public function testEachSlotRejectsAScalarItemWhenItsShapeReadsTheKeyAsANestedScopeToo(): void
    {
        // The shape renders 'box' as a value *and* reads it as a child scope, so
        // an item cannot stand in for the scope alone.
        $shape = Compile::shape(ul(Slot::each('xs', div(
            Slot::value('box'),
            Slot::child('box', em('inner'))
        ))));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "slot 'xs[]' must be an array, string given. The item shape of this slot reads 'box', so each item must be an array."
        );

        $shape(['xs' => ['x']]);
    }

    public function testEachSlotRejectsAScalarItemWhenItsShapeOnlyReadsACondition(): void
    {
        // A condition is a truthiness read: binding a scalar to it would pick a
        // branch per item, which the shape never asked for.
        $shape = Compile::shape(ul(Slot::each('xs', li(Slot::if('flag', em('on'))))));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "slot 'xs[]' must be an array, string given. The item shape of this slot reads 'flag', so each item must be an array."
        );

        $shape(['xs' => ['x']]);
    }

    public function testIfSlotInAttributePositionIsRejected(): void
    {
        $this->expectException(LogicException::class);

        Compile::shape(div('x')->class(Slot::if('on', span('y'))))->compile();
    }
}
