<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/UnitFactory.php';
require_once __DIR__ . '/Support/ModernUnit.php';

use PHPUnit\Framework\TestCase;
use Pure\Compile\Compile;
use Pure\Compile\Internal\ArtifactCompiler;
use Pure\Component\Call;

use function Pure\Component\component;

use Pure\Core\DevMode;
use Pure\Core\Markup;
use Pure\Core\MissingSlotException;
use Pure\Core\Raw;
use Pure\Core\Slot;

use function Pure\HTML\{div, h2, li, p, section, ul};

class CallTest extends TestCase
{
    private static ?string $dir = null;

    private static string $suffix = '';

    protected function setUp(): void
    {
        parent::setUp();

        Compile::cachePath(null);
        Compile::flush();
        DevMode::reset();

        self::fixtures();
    }

    protected function tearDown(): void
    {
        DevMode::reset();

        parent::tearDown();
    }

    public function testFluentCallBindsPropsAndChildren(): void
    {
        $html = FluentCard(h2('Pro'), p('Everything'))
            ->type('Free')
            ->features([['value' => '10 users'], ['value' => '2 GB']])
            ->text('Sign up')
            ->class('btn btn-lg')
            ->render();

        $this->assertSame(
            '<div class="card"><h2>Pro</h2><p>Everything</p>'
            . '<h2 class="card-title">Free</h2><p>Sign up</p>'
            . '<ul class="list"><li>10 users</li><li>2 GB</li></ul></div>',
            $html
        );
    }

    public function testCallIsMarkupAndNestsLikeATag(): void
    {
        $call = FluentCard(h2('Pro'))->type('Free')->features([])->text('Sign up');

        $this->assertInstanceOf(Call::class, $call);
        $this->assertInstanceOf(Markup::class, $call);
        $this->assertSame($call->render(), (string)$call);
        $this->assertSame(
            '<section><div class="card"><h2>Pro</h2><h2 class="card-title">Free</h2><p>Sign up</p><ul class="list"></ul></div></section>',
            section($call)->render()
        );
    }

    public function testChildlessCallRendersEmptyChildren(): void
    {
        $this->assertSame(
            '<div class="card"><h2 class="card-title">Free</h2><p>Sign up</p><ul class="list"></ul></div>',
            FluentCard()->type('Free')->features([])->text('Sign up')->render()
        );
    }

    public function testChildrenOnAComponentWithoutAChildrenSlotThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("component 'FluentPlain' does not read children");

        component('FluentPlain', 'child')->text('x')->render();
    }

    public function testArrayChildrenAreFlattened(): void
    {
        $this->assertSame(
            '<div class="card"><h2>Pro</h2><p>Nested</p><h2 class="card-title">Free</h2><p>Sign up</p><ul class="list"></ul></div>',
            FluentCard([h2('Pro'), [p('Nested')]])->type('Free')->features([])->text('Sign up')->render()
        );
    }

    public function testTextChildrenAreEscapedAndRawIsVerbatim(): void
    {
        $this->assertSame(
            '<div class="card">&lt;b&gt;x&lt;/b&gt;<h2 class="card-title">Free</h2><p>Sign up</p><ul class="list"></ul></div>',
            FluentCard('<b>x</b>')->type('Free')->features([])->text('Sign up')->render()
        );

        $this->assertSame(
            '<div class="card"><b>y</b><h2 class="card-title">Free</h2><p>Sign up</p><ul class="list"></ul></div>',
            FluentCard(Raw::of('<b>y</b>'))->type('Free')->features([])->text('Sign up')->render()
        );
    }

    public function testSlotChildIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('a Slot cannot be a child of a call');

        FluentCard(Slot::value('x'))->type('Free')->features([])->text('Sign up')->render();
    }

    public function testChildrenCannotBeAProp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pass children to the call itself');

        FluentCard()->children('x');
    }

    public function testPropTakesExactlyOneValue(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage("prop 'type()' takes exactly one value");

        FluentCard()->type('a', 'b');
    }

    public function testSlotPropIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("prop 'type' cannot be a Slot");

        FluentCard()->type(Slot::value('x'));
    }

    public function testPropsSetSeveralValuesAtOnce(): void
    {
        $this->assertSame(
            '<div class="card"><h2 class="card-title">Free</h2><p>Sign up</p><ul class="list"></ul></div>',
            FluentCard()->props(['type' => 'Free', 'features' => [], 'text' => 'Sign up'])->render()
        );

        $this->assertSame(
            '<div class="card"><h2 class="card-title">Free</h2><p>Sign up</p><ul class="list"></ul></div>',
            FluentCard()->props(['type' => 'Free', 'features' => [], 'text' => 'Sign up', 'unused' => null])->render()
        );
    }

    public function testClassAndStyleJoinLikeTagSetters(): void
    {
        $this->assertSame('<div class="text-muted">x</div>', FluentPlain()->text('x')->class('text-muted')->render());
        $this->assertSame('<div class="btn active">x</div>', FluentPlain()->text('x')->class('btn', 'active')->render());
        $this->assertSame('<div class="btn">x</div>', FluentPlain()->text('x')->class('btn', false)->render());
        $this->assertSame('<div style="color: red;">x</div>', FluentPlain()->text('x')->style(['color' => 'red'])->render());
        $this->assertSame('<div>x</div>', FluentPlain()->text('x')->class('')->class(null)->render());
    }

    public function testNullPropLeavesThePropUnset(): void
    {
        $this->assertSame('<div>x</div>', FluentPlain()->text('x')->class(null)->render());

        $this->expectException(MissingSlotException::class);
        $this->expectExceptionMessage("slot 'text' is required but was not provided");

        FluentPlain()->text(null)->render();
    }

    public function testPrepareTransformsPropsAndEnforcesItsSignature(): void
    {
        $this->assertSame(
            '<div><h2>COLUMNS</h2><div class="row g-4"><b>a</b><b>b</b></div></div>',
            FluentSection()->section('columns')->class('row g-4')->item(static fn (string $value): string => "<b>{$value}</b>")->render()
        );

        try {
            component('FluentSection')->section('columns')->class('row')->render();
            $this->fail('Expected a missing prop error.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame("component 'FluentSection': missing prop 'item'.", $error->getMessage());
        }

        try {
            component('FluentSection')->section('columns')->class('row')->item('x')->sectoin('y')->render();
            $this->fail('Expected an unknown prop error.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame(
                "component 'FluentSection': unknown prop 'sectoin' (did you mean 'section'?); "
                . "prepare() accepts 'section', 'class', 'item'.",
                $error->getMessage()
            );
        }

        try {
            component('FluentSection')->section(42)->class('row')->item(static fn (): string => 'x')->render();
            $this->fail('Expected a type error.');
        } catch (TypeError $error) {
            $this->assertStringContainsString("component 'FluentSection': ", $error->getMessage());
            $this->assertStringContainsString('must be of type string, int given', $error->getMessage());
        }
    }

    public function testAMisspelledRequiredPropNamesTheTypoRatherThanTheGap(): void
    {
        try {
            component('FluentSection')->sectoin('columns')->item('x')->render();
            $this->fail('Expected an unknown prop error.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame(
                "component 'FluentSection': unknown prop 'sectoin' (did you mean 'section'?); "
                . "prepare() accepts 'section', 'class', 'item'.",
                $error->getMessage()
            );
        }
    }

    public function testEveryUnknownPropIsListedWithItsOwnSuggestion(): void
    {
        try {
            component('FluentSection')->sectoin('columns')->clas('row')->thing('x')->render();
            $this->fail('Expected an unknown prop error.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame(
                "component 'FluentSection': unknown prop 'sectoin' (did you mean 'section'?), "
                . "'clas' (did you mean 'class'?), 'thing'; "
                . "prepare() accepts 'section', 'class', 'item'.",
                $error->getMessage()
            );
        }
    }

    public function testUnknownPropIsReportedByTheGuard(): void
    {
        Compile::guard(true);

        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            if ($errno === E_USER_WARNING) {
                $warnings[] = $message;

                return true;
            }

            return false;
        });

        try {
            $this->assertSame('<div>x</div>', FluentPlain()->text('x')->tex('y')->render());
        } finally {
            Compile::guard(false);
            restore_error_handler();
        }

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString("unknown data key 'tex' (did you mean 'text'?)", $warnings[0]);
    }

    public function testDeprecatedPropWarnsInDevelopment(): void
    {
        Compile::guard(true);

        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            if ($errno === E_USER_WARNING) {
                $warnings[] = $message;

                return true;
            }

            return false;
        });

        try {
            FluentDeclared()->text('hi')->icon(Raw::of('<svg/>'))->style('x')->render();
            FluentDeclared()->text('hi')->icon(Raw::of('<svg/>'))->style('x')->render();
        } finally {
            Compile::guard(false);
            restore_error_handler();
        }

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString("component 'FluentDeclared': prop 'style' is deprecated: use style()", $warnings[0]);
    }

    public function testTrustedPropWarnsForValuesThatAreNotMarkup(): void
    {
        Compile::guard(true);

        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            if ($errno === E_USER_WARNING) {
                $warnings[] = $message;

                return true;
            }

            return false;
        });

        try {
            FluentDeclared()->text('hi')->icon('<svg/>')->render();
        } finally {
            Compile::guard(false);
            restore_error_handler();
        }

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString("prop 'icon' is declared as markup (#[Trusted]) but received string", $warnings[0]);
        $this->assertStringContainsString('Raw::of()', $warnings[0]);
    }

    public function testTrustedPropAcceptsMarkupAndArraysOfIt(): void
    {
        Compile::guard(true);

        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            if ($errno === E_USER_WARNING) {
                $warnings[] = $message;

                return true;
            }

            return false;
        });

        try {
            FluentDeclared()->text('hi')->icon(Raw::of('<svg/>'))->render();
            FluentDeclared()->text('hi')->icon([Raw::of('<a/>'), Raw::of('<b/>')])->render();
        } finally {
            Compile::guard(false);
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
    }

    public function testDeclarationsAreSilentWithoutTheGuard(): void
    {
        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            if ($errno === E_USER_WARNING) {
                $warnings[] = $message;

                return true;
            }

            return false;
        });

        try {
            FluentDeclared()->text('hi')->icon('<svg/>')->render();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
    }

    public function testCallRendersThroughTheArtifactWhenFresh(): void
    {
        $file = self::dir() . '/FluentArtifact.cmp.php';
        $calls = 0;
        UnitFactory::set('FluentArtifact', static function () use (&$calls): \Pure\Compile\Shape {
            $calls++;

            return Compile::shape(div(h2(Slot::value('title'))));
        });

        ArtifactCompiler::writeUnit($file, Compile::shape(div(h2(Slot::value('title')))));
        clearstatcache();
        Compile::flush();

        $this->assertSame('<div><h2>Artifact</h2></div>', FluentArtifact()->title('Artifact')->render());
        $this->assertSame(0, $calls, 'a fresh artifact must serve the unit without the factory');
    }

    public function testUnitFilePatternWithAOneLineCallFunction(): void
    {
        $name = 'Pattern' . self::suffix();
        $file = self::dir() . '/' . $name . '.cmp.php';

        file_put_contents($file, <<<PHP
            <?php

            declare(strict_types=1);

            use Pure\\Compile\\Compile;
            use Pure\\Component\\Call;
            use Pure\\Core\\Slot;

            use function Pure\\Component\\{component, register};
            use function Pure\\HTML\\div;

            function {$name}(mixed ...\$children): Call { return component(__FUNCTION__, ...\$children); }
            register({$name}(...), static fn (): \\Pure\\Compile\\Shape => Compile::shape(
                div(Slot::value('text'))
            ));
            PHP);

        require $file;

        if (!is_callable($name)) {
            $this->fail("the unit file must define {$name}()");
        }

        $this->assertSame('<div>one line</div>', $name()->text('one line')->render());
    }

    /**
     * Load the units of this test class, idempotently: another test class may
     * reset the registry between two passes over this one, so a missing name
     * makes ModernUnit include() the file again and re-run its register().
     */
    private static function fixtures(): void
    {
        UnitFactory::set('FluentCard', static fn (): \Pure\Compile\Shape => Compile::shape(
            div(
                Slot::raw('children'),
                h2(Slot::value('type'))->class('card-title'),
                p(Slot::value('text')),
                ul(Slot::each('features', li(Slot::value('value'))))->class('list')
            )->class('card')
        ));
        ModernUnit::load(self::dir() . '/FluentCard.cmp.php', 'FluentCard');

        UnitFactory::set('FluentPlain', static fn (): \Pure\Compile\Shape => Compile::shape(
            div(Slot::value('text'))
                ->class(Slot::value('class')->default(null))
                ->style(Slot::value('style')->default(null))
        ));
        ModernUnit::load(self::dir() . '/FluentPlain.cmp.php', 'FluentPlain');

        UnitFactory::set('FluentArtifact', static fn (): \Pure\Compile\Shape => Compile::shape(
            div(h2(Slot::value('title')))
        ));
        ModernUnit::load(self::dir() . '/FluentArtifact.cmp.php', 'FluentArtifact');

        UnitFactory::set('FluentDeclared', static fn (): \Pure\Compile\Shape => Compile::shape(
            div(Slot::value('text'), div(Slot::raw('icon'))->class(Slot::value('style')->default(null)))
        ));
        ModernUnit::load(self::dir() . '/FluentDeclared.cmp.php', 'FluentDeclared', <<<'PREPARE'
            static fn (
                string $text,
                #[\Pure\Component\Trusted]
                mixed $icon,
                #[\Pure\Component\Prop(deprecated: 'use style()')]
                ?string $style = null,
            ): array => [
                'text' => $text,
                'icon' => $icon,
                'style' => $style,
            ]
            PREPARE);

        UnitFactory::set('FluentSection', static fn (): \Pure\Compile\Shape => Compile::shape(
            div(h2(Slot::value('title')), div(Slot::raw('contents'))->class(Slot::value('class')))
        ));
        ModernUnit::load(self::dir() . '/FluentSection.cmp.php', 'FluentSection', <<<'PREPARE'
            static fn (string $section, string $class, callable $item): array => [
                'title' => strtoupper($section),
                'contents' => array_map(
                    static fn (array $record): string => $item($record['value']),
                    [
                        ['value' => 'a'],
                        ['value' => 'b'],
                    ]
                ),
                'class' => $class,
            ]
            PREPARE);
    }

    private static function dir(): string
    {
        if (self::$dir !== null) {
            return self::$dir;
        }

        $dir = sys_get_temp_dir() . '/purephp-call-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o700, true);
        self::$dir = $dir;

        register_shutdown_function(static function () use ($dir): void {
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    @unlink($dir . '/' . $entry);
                }
            }

            @rmdir($dir);
        });

        return $dir;
    }

    private static function suffix(): string
    {
        return self::$suffix !== '' ? self::$suffix : self::$suffix = bin2hex(random_bytes(4));
    }
}
