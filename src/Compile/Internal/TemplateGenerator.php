<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

use LogicException;
use Pure\Core\Slot;
use Pure\Core\Tag;

/**
 * Generates a readable, template-style renderer source for artifacts.
 *
 * The output reads like the page it renders: static markup stays markup,
 * values become `<?= ... ?>`, and control flow uses the alternative syntax
 * (`if (...): ... endif;`, `foreach (...): ... endforeach;`). The echo order is
 * the one CodeGenerator uses, and the expressions are the same, so both
 * sources render byte-identical output.
 *
 * HTML runs carry the rendered bytes, so they are emitted verbatim and never
 * gain whitespace; newlines only appear inside PHP tags, where they are not
 * part of the output.
 *
 * PlainGenerator extends this class for dependency-free views, so the emission
 * helpers below stay available to subclasses.
 *
 * @internal
 */
class TemplateGenerator extends RendererGenerator
{
    /**
     * Fully qualified class => short name, shortened in the template and
     * imported by the artifact.
     */
    private const CLASSES = [
        'Pure\\Compile\\Internal\\TemplateRuntime' => 'TemplateRuntime',
        'Pure\\Compile\\Internal\\SlotRuntime' => 'SlotRuntime',
        'Pure\\Core\\Escaper' => 'Escaper',
        'Pure\\Core\\MissingSlotException' => 'MissingSlotException',
    ];

    /**
     * Value expressions longer than this are wrapped across lines.
     */
    protected const WRAP_AT = 100;

    protected string $code = '';

    /**
     * Whether the current position is inside a `<?php ... ?>` block.
     */
    protected bool $open = true;

    /**
     * Nesting depth of the open control-flow statements.
     */
    protected int $depth = 0;

    /** @var list<string> */
    protected array $closers = [];

    /**
     * Whether a `switch` branch body is open (`switch` has no alternative
     * syntax, so its cases are indented by hand).
     */
    protected bool $caseOpen = false;

    /**
     * Statement indentation level: 2 for the body of a closure at level 1.
     */
    protected int $level = 2;

    /**
     * The interleaved template source of a tree.
     *
     * @param Tag $tree The shape tree to compile.
     * @return string The generated template source.
     */
    public static function source(Tag $tree): string
    {
        $generator = new self();
        $generator->level = 2;

        $generator->code = 'static function (array $v): string {' . "\n";
        $generator->code .= self::indent(1) . "ob_start();\n";
        $generator->code .= self::indent(1) . 'try {';

        (new ShapeWalker($generator))->walk($tree);
        $generator->flushLiteral();
        $generator->openPhp();

        $generator->code .= "\n" . self::indent(1) . "} finally {\n";
        $generator->code .= self::indent(2) . "\$out = (string)ob_get_clean();\n";
        $generator->code .= self::indent(1) . "}\n\n";
        $generator->code .= self::indent(1) . "return \$out;\n";
        $generator->code .= '}';

        return $generator->code;
    }

    /**
     * The class imports the artifact needs for the shortened names in $source.
     *
     * @return list<string>
     */
    public static function imports(string $source): array
    {
        $imports = [];

        foreach (self::CLASSES as $class => $short) {
            if (str_contains($source, $short . '::')) {
                $imports[] = 'use ' . $class . ';';
            }
        }

        return $imports;
    }

    protected function emitLiteral(string $text): void
    {
        if (str_contains($text, '<?')) {
            // Verbatim markup can contain `<?`; echoing it keeps the template
            // parseable at the cost of one less readable line.
            $this->openPhp();
            $this->code .= "\n" . $this->pad() . 'echo ' . var_export($text, true) . ';';

            return;
        }

        $this->closePhp();
        $this->code .= $text;
    }

    protected function emitExpression(string $expression): void
    {
        $expression = $this->format(self::shorten($expression), $this->pad());

        if ($this->open) {
            $this->code .= "\n" . $this->pad() . 'echo ' . $expression . ';';

            return;
        }

        $this->code .= '<?= ' . $expression . ' ?>';
    }

    protected function emitStatement(string $statement): void
    {
        $this->openPhp();
        $this->code .= "\n" . $this->line($this->formatStatement(self::shorten($statement), $this->pad()));
    }

    protected function valueSource(string $kind, Slot $slot, string $dataVar, string $slotPath): string
    {
        return 'TemplateRuntime::' . $kind . '(' . $this->access($dataVar, $slot, $slotPath) . ')';
    }

    protected function attrSource(string $key, Slot $slot, string $dataVar, string $slotPath): string
    {
        return 'TemplateRuntime::attr('
            . $this->access($dataVar, $slot, $slotPath, $slot->name === $key ? '' : var_export($key, true))
            . ')';
    }

    protected function childSource(Slot $slot, string $dataVar, string $slotPath): string
    {
        return 'TemplateRuntime::child(' . $this->access($dataVar, $slot, $slotPath) . ')';
    }

    protected function itemsSource(Slot $slot, string $dataVar, string $slotPath): string
    {
        return 'TemplateRuntime::items(' . $this->access($dataVar, $slot, $slotPath) . ')';
    }

    protected function scopeSource(string $value, string $scopePath, string $hint = ''): string
    {
        return 'TemplateRuntime::scope(' . $value . ', ' . var_export($scopePath, true)
            . ($hint === '' ? '' : ', ' . var_export($hint, true)) . ')';
    }

    /**
     * Argument list of one TemplateRuntime accessor: the data scope, the slot
     * key, an optional head (the attribute name), the slot path when it differs
     * from the key, and the compiled default of an optional slot. The default
     * is passed by name while the path stays implicit, so a reader sees
     * `text($v, 'subtitle', default: 'none')`.
     */
    protected function access(string $dataVar, Slot $slot, string $slotPath, string $head = ''): string
    {
        $arguments = [$dataVar, var_export($slot->name, true)];

        if ($head !== '') {
            $arguments[] = $head;
        }

        if ($slotPath !== $slot->name) {
            $arguments[] = 'path: ' . var_export($slotPath, true);
        }

        if (!$slot->required) {
            $arguments[] = 'default: ' . var_export($slot->default, true);
        }

        return implode(', ', $arguments);
    }

    /**
     * Break a long value expression across lines, with the arguments of a
     * static call on separate lines. Whitespace inside PHP tags is not part of
     * the output, so this only changes how the template reads.
     */
    protected function format(string $expression, string $pad): string
    {
        if (strlen($expression) <= self::WRAP_AT) {
            return $expression;
        }

        return $this->formatCall($expression, $pad) ?? $expression;
    }

    /**
     * Break an assignment of a static call across lines.
     */
    protected function formatStatement(string $statement, string $pad): string
    {
        if (preg_match('/^(\$\w+ = )(.+);$/', $statement, $matches) !== 1) {
            return $statement;
        }

        if (strlen($matches[2]) <= self::WRAP_AT) {
            return $statement;
        }

        $formatted = $this->formatCall($matches[2], $pad);

        return $formatted === null ? $statement : $matches[1] . $formatted . ';';
    }

    /**
     * Put every argument of a static call on its own line.
     */
    protected function formatCall(string $expression, string $pad): ?string
    {
        $open = strpos($expression, '(');

        if ($open === false || !str_ends_with($expression, ')')) {
            return null;
        }

        $head = substr($expression, 0, $open);

        if (preg_match('/^[A-Za-z_]\w*(::[A-Za-z_]\w*)?$/', $head) !== 1) {
            return null;
        }

        $arguments = self::arguments(substr($expression, $open + 1, -1));

        if ($arguments === null) {
            return null;
        }

        return $head . "(\n{$pad}    " . implode(",\n{$pad}    ", $arguments) . "\n{$pad})";
    }

    /**
     * Split a call argument list at top-level commas, ignoring nesting and
     * quoted strings.
     *
     * @return list<string>|null
     */
    private static function arguments(string $arguments): ?array
    {
        $parts = [];
        $depth = 0;
        $quoted = false;
        $start = 0;
        $length = strlen($arguments);

        for ($index = 0; $index < $length; $index++) {
            $char = $arguments[$index];

            if ($quoted) {
                if ($char === '\\') {
                    $index++;
                } elseif ($char === "'") {
                    $quoted = false;
                }

                continue;
            }

            if ($char === "'") {
                $quoted = true;
            } elseif ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim(substr($arguments, $start, $index - $start));
                $start = $index + 1;
            }
        }

        if ($depth !== 0 || $quoted || $parts === []) {
            return null;
        }

        $parts[] = trim(substr($arguments, $start));

        return $parts;
    }

    /**
     * Translate one flat statement to the template form, keeping the statement
     * nesting in step with the indentation.
     */
    protected function line(string $statement): string
    {
        if (str_starts_with($statement, 'case ')) {
            if ($this->caseOpen) {
                $this->depth--;
            }

            $line = $this->pad() . $statement;
            $this->depth++;
            $this->caseOpen = true;

            return $line;
        }

        if ($statement === 'break;' && $this->caseOpen) {
            $line = $this->pad() . $statement;
            $this->caseOpen = false;
            $this->depth--;

            return $line;
        }

        if ($statement === '}') {
            if ($this->caseOpen) {
                $this->caseOpen = false;
                $this->depth--;
            }

            $closer = array_pop($this->closers);
            if ($closer === null) {
                throw new LogicException('unbalanced control flow in the template body.');
            }

            $this->depth--;

            return $this->pad() . $closer;
        }

        if ($statement === '} else {') {
            // The branch body is indented one level deeper than the `if` line;
            // `else:` belongs on the `if` level.
            $this->depth--;
            $line = $this->pad() . 'else:';
            $this->depth++;

            return $line;
        }

        if (str_starts_with($statement, 'if (') || str_starts_with($statement, 'foreach (')) {
            $closer = str_starts_with($statement, 'if (') ? 'endif;' : 'endforeach;';
            $line = $this->pad() . substr($statement, 0, -2) . ':';
            $this->closers[] = $closer;
            $this->depth++;

            return $line;
        }

        if (str_starts_with($statement, 'switch (')) {
            // PHP has no alternative syntax for switch, so the braces stay.
            $line = $this->pad() . $statement;
            $this->closers[] = '}';
            $this->depth++;

            return $line;
        }

        return $this->pad() . $statement;
    }

    /**
     * Shorten the fully qualified classes the generated expressions use, so
     * the template reads like hand-written PHP; the artifact imports them.
     */
    private static function shorten(string $code): string
    {
        foreach (self::CLASSES as $class => $short) {
            $code = str_replace('\\' . $class . '::', $short . '::', $code);
        }

        return $code;
    }

    protected function openPhp(): void
    {
        if (!$this->open) {
            $this->code .= '<?php';
            $this->open = true;
        }
    }

    protected function closePhp(): void
    {
        if ($this->open) {
            $this->code .= ' ?>';
            $this->open = false;
        }
    }

    protected function pad(): string
    {
        return self::indent($this->level + $this->depth);
    }

    private static function indent(int $level): string
    {
        return str_repeat('    ', max(0, $level));
    }
}
