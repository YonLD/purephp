<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pure\Compile\Compile;
use Pure\Compile\Internal\CheckCommand;
use Pure\Component\Registry;
use Pure\Core\DevMode;

class CheckTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/purephp-check-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0o700, true);

        Compile::cachePath(null);
        Compile::flush();
        Registry::reset();
        DevMode::reset();
    }

    protected function tearDown(): void
    {
        Registry::reset();
        $this->remove($this->dir);
        Compile::flush();

        parent::tearDown();
    }

    public function testCleanUnitPasses(): void
    {
        $file = $this->writeFile('clean.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{div, li, ul};

            $item = Compile::shape(li(Slot::value('label')));

            function CleanBox(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CleanBox(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'), ul(Slot::each('items', $item)))
                ),
                prepare: static function (string $title, array $items): array {
                    return ['title' => $title, 'items' => $items];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString("ok: component 'CleanBox' -> {$file}", $result['stdout']);
        $this->assertStringContainsString('checked 1 unit(s): 0 error(s), 0 warning(s).', $result['stdout']);
    }

    public function testTypeMismatchBetweenSlotAndParameterIsAnError(): void
    {
        $file = $this->writeFile('types.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{div, li, ul};

            $item = Compile::shape(li(Slot::value('label')));

            function TypeBox(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(TypeBox(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'), ul(Slot::each('items', $item)))
                ),
                prepare: static function (string $title, string $items): array {
                    return ['title' => $title, 'items' => $items];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        // A unit-level finding names its file without a line; only a finding
        // about a fluent call carries one.
        $this->assertStringContainsString(
            "{$file}: error: component 'TypeBox': slot 'items' is a list slot but parameter \$items is typed string",
            $result['stderr']
        );
        $this->assertStringNotContainsString("slot 'title'", $result['stdout']);
    }

    public function testNullableParameterForARequiredSlotWarns(): void
    {
        $file = $this->writeFile('nullable.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function NullBox(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(NullBox(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))
                ),
                prepare: static function (?string $title): array {
                    return ['title' => $title];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString(
            "{$file}: warning: component 'NullBox': parameter \$title is nullable but slot 'title' is required; binding null throws MissingSlotException",
            $result['stderr']
        );

        $strict = $this->runCheck(['pure', 'check', '--strict', $file]);

        $this->assertSame(1, $strict['code']);
    }

    public function testUnusedParameterWarns(): void
    {
        $file = $this->writeFile('unused.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function UnusedBox(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(UnusedBox(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))
                ),
                prepare: static function (string $title, string $extra): array {
                    return ['title' => $title];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString(
            'parameter $extra is neither used by prepare() nor a slot of the template',
            $result['stderr']
        );
    }

    public function testArrowPrepareReturnsOneArrayLiteral(): void
    {
        $file = $this->writeFile('arrow-ok.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\{component, register};
            use function Pure\HTML\div;

            function CheckArrowOk(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckArrowOk(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))
                ),
                prepare: static fn (string $title): array => ['title' => $title],
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringNotContainsString('does not return one array literal', $result['stdout']);
        $this->assertStringContainsString('0 error(s), 0 warning(s).', $result['stdout']);
    }

    public function testArrowPrepareKeysAreComparedAgainstTheSlots(): void
    {
        $file = $this->writeFile('arrow-miss.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\{component, register};
            use function Pure\HTML\div;

            function CheckArrowMiss(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckArrowMiss(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'), Slot::value('desc'))
                ),
                prepare: static fn (string $title): array => ['title' => $title],
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "required slot 'desc' is not returned by prepare()",
            $result['stderr']
        );
    }

    public function testArrowPrepareUnusedParameterWarns(): void
    {
        $file = $this->writeFile('arrow-unused.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\{component, register};
            use function Pure\HTML\div;

            function CheckArrowUnused(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckArrowUnused(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))
                ),
                prepare: static fn (string $title, string $extra): array => ['title' => $title],
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString(
            'parameter $extra is neither used by prepare() nor a slot of the template',
            $result['stderr']
        );
    }

    public function testUnitWithoutPrepareOrCallFunctionPointsAtTheCallSites(): void
    {
        $file = $this->writeFile('page.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Registry;
            use Pure\Core\Slot;

            use function Pure\HTML\div;

            // The internal primitive registers under a name with no call
            // function: the public register() always derives one.
            Registry::register('PageBox', __FILE__, static fn (): \Pure\Compile\Shape => Compile::shape(
                div(Slot::value('title'))
            ));
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString(
            "no prepare() and no function named 'PageBox'; the props are the template slots, so the call sites are checked instead",
            $result['stdout']
        );
        $this->assertStringContainsString('0 error(s), 0 warning(s), 1 note(s).', $result['stdout']);
    }

    public function testFunctionThatDoesNotReturnACallIsAnError(): void
    {
        $file = $this->writeFile('plain-function.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            register(PlainFunctionBox(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                div(Slot::value('title'))
            ));

            function PlainFunctionBox(string $title): string
            {
                return $title;
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "'PlainFunctionBox()' must return Pure\\Component\\Call (it returns string); register a prepare() contract or return component(...) from it",
            $result['stderr']
        );
    }

    public function testShapeFileWithConflictingSlotKindsIsAnError(): void
    {
        $file = $this->writeFile('conflict.shape.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\HTML\{div, li, ul};

            $item = Compile::shape(li(Slot::value('label')));

            return Compile::shape(
                div(Slot::value('items'), ul(Slot::each('items', $item)))
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "slot 'items' is used as a value or raw slot and as a child or list scope; one data key cannot be both",
            $result['stderr']
        );
    }

    public function testCleanShapeFilePasses(): void
    {
        $file = $this->writeFile('clean.shape.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\HTML\div;

            return Compile::shape(div(Slot::value('title')));
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString("ok: {$file} (shape)", $result['stdout']);
    }

    public function testDirectoryIsSearchedRecursively(): void
    {
        mkdir($this->dir . '/views', 0o700);
        $this->writeFile('views/one.shape.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Pure\\Compile\\Compile::shape(Pure\\HTML\\div('x'));\n");

        $result = $this->runCheck(['pure', 'check', $this->dir]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString('checked 1 unit(s)', $result['stdout']);
    }

    public function testCallFunctionCanResolveTheUnitByFilePath(): void
    {
        $file = $this->writeFile('namespaced.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Blocks;

            use Pure\Compile\Compile;
            use Pure\Component\Call;
            use Pure\Core\Slot;

            use function Pure\Component\{component, register};
            use function Pure\HTML\div;

            register(NamespacedBox(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                div(Slot::value('title'))
            ));

            function NamespacedBox(): Call
            {
                return component(__FILE__);
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString(
            'fluent unit: its props are the template slots, so the call sites are checked instead',
            $result['stdout']
        );
        $this->assertStringContainsString('0 error(s), 0 warning(s), 1 note(s).', $result['stdout']);
    }

    public function testFluentUnitChecksPrepareAgainstTheSlots(): void
    {
        $file = $this->writeFile('fluent.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{div, h2};

            function CheckFluent(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckFluent(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(h2(Slot::value('title')), div(Slot::raw('contents')))
                ),
                prepare: static function (string $section, string $class): array {
                    return ['title' => strtoupper($section), 'contents' => $class];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString('0 error(s), 0 warning(s).', $result['stdout']);
    }

    public function testFluentUnitPrepareMismatchIsReported(): void
    {
        $file = $this->writeFile('fluent-typo.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{div, h2};

            function CheckFluentTypo(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckFluentTypo(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(h2(Slot::value('title')))
                ),
                prepare: static function (string $section): array {
                    return ['titel' => strtoupper($section)];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "prepare() returns 'titel' but the template does not read it (did you mean 'title'?)",
            $result['stderr']
        );
        $this->assertStringContainsString(
            "required slot 'title' is not returned by prepare()",
            $result['stderr']
        );
    }

    public function testFluentUnitWithAComputedPrepareResultIsNotCompared(): void
    {
        $file = $this->writeFile('fluent-computed.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckFluentComputed(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckFluentComputed(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))
                ),
                prepare: static function (string $title): array {
                    $data = ['title' => $title];

                    return $data;
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString('prepare() does not return one array literal', $result['stdout']);
    }

    public function testDeclaredSlotsCarryTheBindingsOfAComputedPrepare(): void
    {
        $file = $this->writeFile('fluent-declared.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{div, h2};

            function CheckDeclared(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckDeclared(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(h2(Slot::value('title')), div(Slot::raw('contents')))
                ),
                prepare: static function (
                    #[Prop(slot: 'title')] string $text,
                    #[Prop(slot: 'contents')] string $body,
                ): array {
                    $data = ['title' => strtoupper($text), 'contents' => $body];

                    return $data;
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString('its bindings are read from the #[Prop] declarations', $result['stdout']);
        $this->assertStringContainsString('0 error(s), 0 warning(s), 1 note(s).', $result['stdout']);
    }

    public function testUndeclaredRequiredSlotIsAnErrorWhenPrepareIsComputed(): void
    {
        $file = $this->writeFile('fluent-undeclared.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{div, h2};

            function CheckUndeclared(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckUndeclared(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(h2(Slot::value('title')), div(Slot::raw('contents')))
                ),
                prepare: static function (
                    #[Prop(slot: 'title')] string $text,
                    string $body,
                ): array {
                    $data = ['title' => strtoupper($text), 'contents' => $body];

                    return $data;
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "required slot 'contents' is not covered by any declaration and prepare() does not return a readable array literal",
            $result['stderr']
        );
    }

    public function testDeclaredSlotMustBeReturnedByAPrepareLiteral(): void
    {
        $file = $this->writeFile('fluent-declared-literal.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{div, h2};

            function CheckDeclaredLiteral(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckDeclaredLiteral(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(h2(Slot::value('title')))
                ),
                prepare: static function (#[Prop(slot: 'title')] string $text): array {
                    return ['titel' => $text];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "prop \$text declares slot 'title', which prepare() does not return",
            $result['stderr']
        );
    }

    public function testDeclaredSlotMustBeReadByTheTemplate(): void
    {
        $file = $this->writeFile('fluent-declared-typo.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{div, h2};

            function CheckDeclaredTypo(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckDeclaredTypo(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(h2(Slot::value('title')))
                ),
                prepare: static function (#[Prop(slot: 'titel')] string $text): array {
                    return ['titel' => $text];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "prop \$text declares slot 'titel', which the template does not read (did you mean 'title'?)",
            $result['stderr']
        );
    }

    public function testTwoPropsDeclaringOneSlotAreAnError(): void
    {
        $file = $this->writeFile('fluent-declared-duplicate.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckDeclaredDuplicate(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckDeclaredDuplicate(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))
                ),
                prepare: static function (
                    #[Prop(slot: 'title')] string $text,
                    #[Prop(slot: 'title')] string $heading,
                ): array {
                    return ['title' => $text . $heading];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "props \$text and \$heading declare the same slot 'title'",
            $result['stderr']
        );
    }

    public function testDeclaredRequiredMustMatchTheSignature(): void
    {
        $file = $this->writeFile('fluent-declared-required.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckDeclaredRequired(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckDeclaredRequired(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'), Slot::value('class')->default(''))
                ),
                prepare: static function (
                    #[Prop(required: false)] string $title,
                    #[Prop(required: true)] ?string $class = null,
                ): array {
                    return ['title' => $title, 'class' => $class];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            'prop $title is declared optional but its parameter has no default value; callers must pass it',
            $result['stderr']
        );
        $this->assertStringContainsString(
            'prop $class is declared required but its parameter has a default value; callers may omit it',
            $result['stderr']
        );
    }

    public function testDeclaredItemIsCheckedAgainstTheItemShape(): void
    {
        $file = $this->writeFile('fluent-declared-item.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{li, ul};

            function CheckDeclaredItem(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckDeclaredItem(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    ul(Slot::each('features', li(Slot::value('value'))))
                ),
                prepare: static function (#[Prop(item: 'value')] array $features): array {
                    return [
                        'features' => array_map(static fn (string $feature): array => ['value' => $feature], $features),
                    ];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString('0 error(s), 0 warning(s).', $result['stdout']);
    }

    public function testDeclaredItemMismatchesAreReported(): void
    {
        $this->writeFile('item-wrong.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{li, ul};

            function CheckItemWrong(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckItemWrong(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    ul(Slot::each('features', li(Slot::value('value'))))
                ),
                prepare: static function (#[Prop(item: 'vaule')] array $features): array {
                    return ['features' => $features];
                }
            );
            PHP);

        $this->writeFile('item-multi.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{li, ul};

            function CheckItemMulti(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckItemMulti(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    ul(Slot::each('features', li(Slot::value('value'), Slot::value('url'))))
                ),
                prepare: static function (#[Prop(item: 'value')] array $features): array {
                    return ['features' => $features];
                }
            );
            PHP);

        $this->writeFile('item-text.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckItemText(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckItemText(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))
                ),
                prepare: static function (#[Prop(item: 'value')] string $title): array {
                    return ['title' => $title];
                }
            );
            PHP);

        $this->writeFile('item-static.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{li, ul};

            function CheckItemStatic(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckItemStatic(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    ul(Slot::each('features', li('static')))
                ),
                prepare: static function (#[Prop(item: 'value')] array $features): array {
                    return ['features' => $features];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $this->dir]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "prop \$features declares one item slot 'vaule' but the item shape of slot 'features' reads 'value' (did you mean 'value'?)",
            $result['stderr']
        );
        $this->assertStringContainsString(
            "prop \$features declares one item slot 'value' but the item shape of slot 'features' reads 'value', 'url'",
            $result['stderr']
        );
        $this->assertStringContainsString(
            "prop \$title declares item: 'value' but slot 'title' is not a list slot",
            $result['stderr']
        );
        $this->assertStringContainsString(
            "prop \$features declares item: 'value' but the item shape of slot 'features' reads no slots",
            $result['stderr']
        );
    }

    public function testDeprecatedPropIsReportedAtCallSites(): void
    {
        $this->writeFile('deprecated-target.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckDeprecated(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckDeprecated(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))->style(Slot::value('style'))
                ),
                prepare: static function (
                    #[Prop(slot: 'title')] string $title,
                    #[Prop(deprecated: 'use class()')] string $style = '',
                ): array {
                    return ['title' => $title, 'style' => $style];
                }
            );
            PHP);

        $this->writeFile('deprecated-calls.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;

            use function Pure\Component\register;

            function CheckDeprecatedPage(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckDeprecatedPage(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                \Pure\HTML\div()
            ));

            function checkDeprecatedBody(): string
            {
                return (string) CheckDeprecated('Home')->style('color: red');
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $this->dir]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString(
            "component 'CheckDeprecated': the call binds 'style', which is deprecated: use class()",
            $result['stderr']
        );
        $this->assertStringContainsString('0 error(s), 1 warning(s), 1 note(s).', $result['stdout']);
    }

    public function testTrustedPropMustBindARawSlot(): void
    {
        $this->writeFile('trusted-text.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Trusted;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckTrustedText(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckTrustedText(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))
                ),
                prepare: static function (#[Trusted] string $title): array {
                    return ['title' => $title];
                }
            );
            PHP);

        $this->writeFile('trusted-raw.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Trusted;
            use Pure\Core\Markup;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckTrustedRaw(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckTrustedRaw(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::raw('icon'))
                ),
                prepare: static function (#[Trusted] Markup $icon): array {
                    return ['icon' => $icon];
                }
            );
            PHP);

        $this->writeFile('trusted-mixed.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Trusted;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckTrustedMixed(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckTrustedMixed(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::raw('body'), Slot::value('body'))
                ),
                prepare: static function (#[Trusted] mixed $body): array {
                    return ['body' => $body];
                }
            );
            PHP);

        $this->writeFile('trusted-unread.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Trusted;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckTrustedUnread(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckTrustedUnread(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div()
                ),
                prepare: static function (#[Trusted] mixed $extra): array {
                    return ['extra' => $extra];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $this->dir]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString("ok: component 'CheckTrustedRaw'", $result['stdout']);
        $this->assertStringContainsString(
            "prop \$title is declared as markup (#[Trusted]) but slot 'title' is a text slot",
            $result['stderr']
        );
        $this->assertStringContainsString(
            'prop $title is declared as markup (#[Trusted]) but typed string; type it Markup|Stringable (or mixed) to accept markup, or drop the attribute and wrap the value in Raw::of() at the call site',
            $result['stderr']
        );
        $this->assertStringContainsString(
            "prop \$body is declared as markup (#[Trusted]) but slot 'body' is also read as a text slot",
            $result['stderr']
        );
        $this->assertStringContainsString(
            "prop \$extra is declared as markup (#[Trusted]) but slot 'extra' is not read by the template",
            $result['stderr']
        );
    }

    public function testBindsDeclaresTheKeysOfAComputedPrepare(): void
    {
        $this->writeFile('binds-covered.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Binds;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckBindsCovered(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckBindsCovered(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'), div(Slot::raw('desc')))
                ),
                prepare: #[Binds('title', 'desc')] static function (): array {
                    $data = ['title' => 'Pricing', 'desc' => 'Plans'];

                    return $data;
                }
            );
            PHP);

        $this->writeFile('binds-uncovered.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Binds;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckBindsUncovered(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckBindsUncovered(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'), div(Slot::raw('desc')))
                ),
                prepare: #[Binds('title')] static function (): array {
                    $data = ['title' => 'Pricing'];

                    return $data;
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $this->dir]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            'its bindings are read from the #[Binds] declarations',
            $result['stdout']
        );
        $this->assertStringContainsString(
            "required slot 'desc' is not covered by any declaration and prepare() does not return a readable array literal",
            $result['stderr']
        );
    }

    public function testBindsMustMatchAReadableLiteral(): void
    {
        $file = $this->writeFile('binds-literal.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Binds;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckBindsLiteral(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckBindsLiteral(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'), div(Slot::raw('desc')))
                ),
                prepare: #[Binds('title', 'titel')] static function (): array {
                    return ['title' => 'Pricing', 'desc' => 'Plans'];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString("#[Binds] declares 'titel', which prepare() does not return", $result['stderr']);
    }

    public function testCallSiteItemKeysAreCheckedAgainstTheItemShape(): void
    {
        $this->writeFile('items-target.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{a, li, ul};

            function CheckItems(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckItems(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                ul(Slot::each('links', li(a(Slot::value('text'))->href(Slot::value('href')))))
            ));
            PHP);

        $this->writeFile('items-declared.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Prop;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{a, li, ul};

            function CheckItemsDeclared(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckItemsDeclared(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    ul(Slot::each('links', li(a(Slot::value('text'))->href(Slot::value('href')))))
                ),
                prepare: static function (#[Prop(slot: 'links')] array $rows): array {
                    return ['links' => $rows];
                }
            );
            PHP);

        $this->writeFile('items-calls.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;

            use function Pure\Component\register;

            function CheckItemsPage(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckItemsPage(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                \Pure\HTML\div()
            ));

            function checkItemsBody(): string
            {
                return (string) CheckItems('x')->links([['text' => 'Team', 'href' => '#']])
                    . (string) CheckItems('x')->links([['text' => 'A', 'href' => '#'], ['txet' => 'B', 'href' => '#']])
                    . (string) CheckItems('x')->links([['text' => 'Team']])
                    . (string) CheckItemsDeclared('x')->rows([['text' => 'Team', 'href' => '#']])
                    . (string) CheckItems('x')->links(['a', 'b']);
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $this->dir]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "item 2 of 'links' binds 'txet', which the item shape of slot 'links' does not read (did you mean 'text'?)",
            $result['stderr']
        );
        $this->assertStringContainsString(
            "item 2 of 'links' does not provide 'text', which the item shape of slot 'links' requires",
            $result['stderr']
        );
        $this->assertStringContainsString(
            "item 1 of 'links' does not provide 'href', which the item shape of slot 'links' requires",
            $result['stderr']
        );
        $this->assertStringNotContainsString("of 'rows'", $result['stdout']);
        $this->assertStringNotContainsString("binds 'a'", $result['stdout']);
        $this->assertStringContainsString('3 error(s)', $result['stdout']);
    }

    public function testFluentCallSitePropsAreCheckedAgainstTheTarget(): void
    {
        $this->writeFile('target.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckTarget(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckTarget(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                div(Slot::value('title'))
            ));
            PHP);

        $page = $this->writeFile('page-calls.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\section;

            function CheckPageCalls(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckPageCalls(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                section(Slot::raw('body'))
            ));

            function checkPageCallsBody(): string
            {
                return (string) CheckTarget('child')->titel('x');
            }

            function checkPageCallsDynamic(array $props): string
            {
                return (string) CheckTarget('child')->titel(...$props);
            }

            function checkPageCallsChildren(): string
            {
                return (string) CheckTarget('child')->children('x');
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $this->dir]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            "component 'CheckTarget': the call binds 'titel', which the target does not accept (did you mean 'title'?)",
            $result['stderr']
        );
        $this->assertStringContainsString(
            "component 'CheckTarget': pass children to the call itself, e.g. CheckTarget(\$children)",
            $result['stderr']
        );
        $this->assertStringNotContainsString('titel(...$props)', $result['stdout']);
    }

    public function testCallSiteFindingsCarryTheLineOfTheSetterOnStderr(): void
    {
        $this->writeFile('target-line.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\{div, li, ul};

            $item = Compile::shape(li(Slot::value('text')));

            function CheckLineTarget(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckLineTarget(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                div(Slot::value('title'), ul(Slot::each('links', $item)))
            ));
            PHP);

        $page = $this->writeFile('page-line.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckLinePage(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckLinePage(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                div()
            ));

            function checkLineTargetCall(): string
            {
                return (string) CheckLineTarget('child')->titel('x');
            }

            function checkLineChildrenCall(): string
            {
                return (string) CheckLineTarget('child')->children('x');
            }

            function checkLineItemCall(): string
            {
                return (string) CheckLineTarget('child')->links([['txet' => 'x']]);
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $this->dir]);

        $this->assertSame(1, $result['code']);

        // All three kinds of call-site finding point at the line of the setter
        // that caused them, not at the top of the file.
        $this->assertStringContainsString(
            "{$page}:21: error: component 'CheckLineTarget': the call binds 'titel', "
            . "which the target does not accept (did you mean 'title'?)",
            $result['stderr']
        );
        $this->assertStringContainsString(
            "{$page}:26: error: component 'CheckLineTarget': pass children to the call itself, "
            . 'e.g. CheckLineTarget($children)',
            $result['stderr']
        );
        $this->assertStringContainsString(
            "{$page}:31: error: component 'CheckLineTarget': item 1 of 'links' binds 'txet', "
            . "which the item shape of slot 'links' does not read (did you mean 'text'?)",
            $result['stderr']
        );
        $this->assertStringNotContainsString('error:', $result['stdout']);
    }

    public function testEverythingAfterADoubleDashIsAPath(): void
    {
        $file = $this->writeFile('dashed.shape.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use function Pure\HTML\div;

            return div();
            PHP);

        // Without the separator this reads as an unknown option and exits 2.
        $option = $this->runCheck(['pure', 'check', '--dashed.shape.php']);
        $this->assertSame(2, $option['code']);
        $this->assertStringContainsString("unknown option '--dashed.shape.php'", $option['stderr']);

        $result = $this->runCheck(['pure', 'check', '--', $file]);
        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString("ok: {$file} (shape)", $result['stdout']);
    }

    public function testPrepareKeysAreReadFromAnInterpolatedLiteral(): void
    {
        $file = $this->writeFile('fluent-interpolated.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function CheckFluentInterpolated(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckFluentInterpolated(...),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(
                    div(Slot::value('title'))->style(Slot::value('style')->default(null))
                ),
                prepare: static function (string $title, string $bg): array {
                    return [
                        'title' => $title,
                        'style' => "background-image: url('{$bg}');",
                    ];
                }
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringNotContainsString('prepare() does not return one array literal', $result['stdout']);
        $this->assertStringContainsString('0 error(s), 0 warning(s).', $result['stdout']);
    }

    public function testFluentUnitWithoutPreparePointsAtTheCallSites(): void
    {
        $file = $this->writeFile('fluent-plain.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Call;
            use Pure\Core\Slot;

            use function Pure\Component\{component, register};
            use function Pure\HTML\div;

            register(CheckFluentPlain(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                div(Slot::value('title'))
            ));

            function CheckFluentPlain(mixed ...$children): Call
            {
                return component('CheckFluentPlain', ...$children);
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString('fluent unit: its props are the template slots', $result['stdout']);
    }

    public function testCallMethodsAreNotReportedAsUnknownProps(): void
    {
        $this->writeFile('target-methods.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Call;
            use Pure\Core\Slot;

            use function Pure\Component\{component, register};
            use function Pure\HTML\div;

            register(CheckTargetMethods(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                div(Slot::value('title'))
            ));

            function CheckTargetMethods(mixed ...$children): Call
            {
                return component('CheckTargetMethods', ...$children);
            }
            PHP);

        $page = $this->writeFile('page-methods.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\section;

            function CheckPageMethods(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(CheckPageMethods(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                section(Slot::raw('body'))
            ));

            function checkPageMethodsBody(): string
            {
                return (string) CheckTargetMethods()->props(['title' => 'a'])->render();
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $this->dir]);

        $this->assertSame(0, $result['code']);
        $this->assertStringNotContainsString("binds 'props'", $result['stdout']);
        $this->assertStringNotContainsString("binds 'render'", $result['stdout']);
    }

    public function testUsageErrors(): void
    {
        $missing = $this->runCheck(['pure', 'check']);
        $this->assertSame(2, $missing['code']);
        $this->assertStringContainsString('check needs at least one file or directory', $missing['stderr']);

        $unknown = $this->runCheck(['pure', 'check', '--nope', $this->dir]);
        $this->assertSame(2, $unknown['code']);
        $this->assertStringContainsString("unknown option '--nope'", $unknown['stderr']);

        $help = $this->runCheck(['pure', 'check', '--help']);
        $this->assertSame(0, $help['code']);
        $this->assertStringContainsString('Usage:', $help['stdout']);
        $this->assertStringContainsString('pure compile --check', $help['stdout']);
    }

    public function testMissingPathIsReported(): void
    {
        $result = $this->runCheck(['pure', 'check', $this->dir . '/missing']);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString('does not exist', $result['stderr']);
    }

    public function testTemplateFunctionMustDeclareAShapeReturnType(): void
    {
        $file = $this->writeFile('attr-template.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Compile\Template;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            function TplBox(mixed ...$children): \Pure\Component\Call
            {
                return \Pure\Component\component(__FUNCTION__, ...$children);
            }

            register(TplBox(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                div(Slot::value('title'))
            ));

            #[Template]
            function tplBoxBroken(): string
            {
                return 'not a shape';
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString(
            '#[Template] function tplBoxBroken() must declare a return type of Pure\\Compile\\Shape or a tag, got string',
            $result['stderr']
        );
    }

    public function testTemplateMarkedFunctionIsNotTakenForTheCallFunction(): void
    {
        $file = $this->writeFile('attr-tpl-skip.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Compile\Template;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            register(TplSkip(...), static fn (): \Pure\Compile\Shape => TplSkip());

            #[Template]
            function TplSkip(): \Pure\Compile\Shape
            {
                static $shape;

                return $shape ??= Compile::shape(div(Slot::value('title')));
            }
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString("no function named 'TplSkip'", $result['stdout']);
    }

    public function testClosureFormRegistrationDerivesNameAndFile(): void
    {
        $file = $this->writeFile('closure-form.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Component\Call;
            use Pure\Core\Slot;

            use function Pure\Component\{component, register};
            use function Pure\HTML\div;

            function ClosureBox(mixed ...$children): Call
            {
                return component(__FUNCTION__, ...$children);
            }

            register(ClosureBox(...), static fn (): \Pure\Compile\Shape => Compile::shape(
                div(Slot::value('title'))
            ));
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString("component 'ClosureBox'", $result['stdout']);
        $this->assertStringNotContainsString('no component unit is registered', $result['stdout'] . $result['stderr']);
        $this->assertStringNotContainsString("no function named 'ClosureBox'", $result['stdout']);
    }

    public function testClosureFormRejectsAnAnonymousClosure(): void
    {
        $file = $this->writeFile('closure-anon.cmp.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Pure\Compile\Compile;
            use Pure\Core\Slot;

            use function Pure\Component\register;
            use function Pure\HTML\div;

            register(
                static fn (): \Pure\Compile\Shape => Compile::shape(div(Slot::value('title'))),
                factory: static fn (): \Pure\Compile\Shape => Compile::shape(div(Slot::value('title'))),
            );
            PHP);

        $result = $this->runCheck(['pure', 'check', $file]);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString('not an anonymous closure', $result['stderr']);
    }

    private function writeFile(string $name, string $code): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $code);

        return $path;
    }

    /**
     * @param list<string> $argv
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runCheck(array $argv): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        $this->assertIsResource($stdout);
        $this->assertIsResource($stderr);

        $command = new CheckCommand(static fn (string $file): array => Registry::unitsFor($file));
        $code = $command->run($argv, $stdout, $stderr);

        rewind($stdout);
        rewind($stderr);

        return [
            'code' => $code,
            'stdout' => (string)stream_get_contents($stdout),
            'stderr' => (string)stream_get_contents($stderr),
        ];
    }

    private function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;

            if (is_dir($path)) {
                $this->remove($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
