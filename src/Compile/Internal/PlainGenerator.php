<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

use LogicException;
use Pure\Compile\CompileException;
use Pure\Core\Escaper;
use Pure\Core\Slot;
use Pure\Core\SlotKind;
use Pure\Core\Tag;

/**
 * Generates a plain PHP view: a template with no library dependency.
 *
 * The view is loaded by extracting the view data into locals, so a root slot
 * reads as an ordinary variable and a nested slot as the array it lives in:
 *
 *     ob_start();
 *     extract($data, EXTR_SKIP);
 *     require 'views/index.plain.php';
 *     $html = (string)ob_get_clean();
 *
 * Values are escaped with `htmlspecialchars()` using the same flags as the
 * compiled renderer, so a plain view renders byte-identical output for
 * ordinary data. A raw slot joins an iterable with `implode()`, like the
 * runtime does. The view starts with the markup of a fragment and with the
 * document header of a document root, so a page keeps its `<!DOCTYPE html>` /
 * XML declaration while an included fragment stays a fragment. The strict slot
 * semantics (MissingSlotException, attributes omitted for null, per-element
 * validation) belong to the runtime renderer and are not part of a plain view:
 * a missing slot is an undefined variable, a null attribute prints an empty
 * value.
 *
 * @internal
 */
final class PlainGenerator extends TemplateGenerator
{
    /**
     * The root data scope of the base class: root slots are locals, not
     * offsets of an array.
     */
    private const ROOT = '$v';

    /**
     * Slot names that must not become locals: the loader's own variables, the
     * variables this generator creates and PHP superglobals.
     */
    private const RESERVED = [
        'this', 'globals', 'data', 'kind',
        '_get', '_post', '_server', '_cookie', '_files', '_env', '_session', '_request',
    ];

    /**
     * The plain view body of a tree, preceded by the document header only when
     * the root tag heads a complete document. A fragment (a div, an inline SVG)
     * starts with its markup, so including the view cannot inject a header into
     * the middle of a document.
     *
     * @param Tag $tree The shape tree to compile.
     * @return string The view source, starting with the header and markup.
     */
    public static function view(Tag $tree): string
    {
        $generator = new self();
        $generator->level = 0;

        (new ShapeWalker($generator))->walk($tree);
        $generator->flushLiteral();

        $body = $generator->code;

        if (!str_starts_with($body, ' ?>')) {
            throw new LogicException('a plain view must start with markup.');
        }

        $header = $tree->isDocumentRoot() ? $tree->documentHeader() : '';

        return '?>' . $header . substr($body, 3);
    }

    protected function valueSource(string $kind, Slot $slot, string $dataVar, string $slotPath): string
    {
        $access = $this->slotData($dataVar, $slot);

        if ($kind === 'raw') {
            // A raw value may be an iterable of stringables; the runtime joins
            // it, and a plain view has no runtime, so it joins with native PHP.
            // `$pureRaw` cannot collide with an extracted local: local() never
            // maps a `pure*` slot name.
            return 'is_iterable($pureRaw = ' . $access . ')'
                . " ? implode('', is_array(\$pureRaw) ? \$pureRaw : iterator_to_array(\$pureRaw, false))"
                . ' : $pureRaw';
        }

        return 'htmlspecialchars((string)' . $access . ", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false)";
    }

    /**
     * Write one attribute: the name stays markup, the value is an echo, like a
     * hand-written view (`href="<?= … ?>"`).
     */
    public function attribute(string $key, string|Slot $value, string $slotPath): void
    {
        if (!$value instanceof Slot) {
            $this->literal(Escaper::attribute($key, $value));

            return;
        }

        if ($value->kind !== SlotKind::Value) {
            throw CompileException::slotInAttributePosition($value->kind, $slotPath);
        }

        $this->literal(' ' . $key . '="');
        $this->expression(
            'htmlspecialchars((string)' . $this->slotData($this->data(), $value)
            . ", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')"
        );
        $this->literal('"');
    }

    protected function itemsSource(Slot $slot, string $dataVar, string $slotPath): string
    {
        return $this->slotData($dataVar, $slot);
    }

    protected function enterChild(Slot $slot, string $slotPath): void
    {
        $this->dataStack[] = $this->slotData($this->data(), $slot);
    }

    protected function enterEach(Slot $slot, string $slotPath): void
    {
        $itemVar = $this->itemVar();
        $this->statement('foreach (' . $this->itemsSource($slot, $this->data(), $slotPath) . ' as ' . $itemVar . ') {');

        // The runtime binds a scalar item to the one key an item shape renders;
        // a plain view has no runtime, so it does the same with plain PHP and
        // stays byte-identical for the data both forms accept.
        $key = RootSlots::itemKey(RootSlots::itemManifest($slot->shape));
        if ($key !== null) {
            $this->statement(
                $itemVar . ' = is_array(' . $itemVar . ') ? ' . $itemVar . ' : ['
                . var_export($key, true) . ' => ' . $itemVar . '];'
            );
        }

        $this->dataStack[] = $itemVar;
    }

    protected function enterIf(Slot $slot, string $slotPath): void
    {
        // Condition slots are never required, so the access carries the default.
        $this->statement('if ((bool)' . $this->slotData($this->data(), $slot) . ') {');
    }

    /**
     * The data expression of one slot: a local variable for root slots (from
     * the extracted view data) and an array offset otherwise, with the
     * compiled default appended for optional slots.
     */
    private function slotData(string $dataVar, Slot $slot): string
    {
        if ($dataVar === self::ROOT) {
            $access = self::local($slot->name) ?? '$data[' . var_export($slot->name, true) . ']';
        } else {
            $access = $dataVar . '[' . var_export($slot->name, true) . ']';
        }

        if (!$slot->required) {
            $access = '(' . $access . ' ?? ' . var_export($slot->default, true) . ')';
        }

        return $access;
    }

    /**
     * The local variable of a root slot, or null when the slot name cannot be
     * an ordinary view variable. Shared with ScopeTypes, which decides whether
     * an `@var` annotation names a local or a `$data` offset.
     */
    public static function local(string $name): ?string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            return null;
        }

        $lower = strtolower($name);

        if (in_array($lower, self::RESERVED, true)) {
            return null;
        }

        if (str_starts_with($lower, 'pure') || preg_match('/^(item|v|kind)\d+$/', $lower) === 1) {
            return null;
        }

        return '$' . $name;
    }
}
