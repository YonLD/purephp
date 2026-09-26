<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

use LogicException;
use Pure\Compile\CompileException;
use Pure\Core\Escaper;
use Pure\Core\Raw;
use Pure\Core\ShapeContract;
use Pure\Core\Slot;
use Pure\Core\SlotKind;
use Pure\Core\Tag;
use WeakMap;

/**
 * Shared shape traversal and expression building for renderer generators.
 *
 * Subclasses only decide how the pieces are written out: CodeGenerator emits
 * flat `$out .= ...;` statements for the runtime path, TemplateGenerator emits
 * an interleaved PHP/HTML template for readable artifacts.
 *
 * @internal
 */
abstract class RendererGenerator implements ShapeVisitor
{
    /** @var list<array{slot: Slot, slotPath: string}> */
    protected array $slotStack = [];

    /** @var list<string> */
    protected array $dataStack = ['$v'];

    /** @var WeakMap<Tag, bool> */
    protected WeakMap $slotCache;

    protected string $literal = '';

    protected int $scope = 0;

    protected function __construct()
    {
        $this->slotCache = new WeakMap();
    }

    /**
     * @param array{tagName: string, selfClose: bool, attrs: array<string, string|Slot>, children: array<int, mixed>} $export
     */
    public function tagOpen(Tag $tag, string $path, array $export): bool
    {
        if (!$this->hasSlots($tag)) {
            // Slot-free subtrees are static markup; the string renderer shares
            // the escaping implementation, so folding is byte-identical.
            $this->literal($tag->render());

            return false;
        }

        $this->literal('<' . $export['tagName']);

        return true;
    }

    public function attribute(string $key, string|Slot $value, string $slotPath): void
    {
        if ($value instanceof Slot) {
            if ($value->kind !== SlotKind::Value) {
                throw CompileException::slotInAttributePosition($value->kind, $slotPath);
            }

            // Attribute slots keep the runtime helper: the ` name="value"` chunk
            // format belongs to Escaper::attribute(), and duplicating it in
            // generated code would split the escaping format across two places.
            $this->expression($this->attrSource($key, $value, $this->data(), $slotPath));

            return;
        }

        $this->literal(Escaper::attribute($key, $value));
    }

    public function tagSelfClose(): void
    {
        $this->literal(' />');
    }

    public function contentStart(): void
    {
        $this->literal('>');
    }

    public function tagClose(string $tagName): void
    {
        $this->literal('</' . $tagName . '>');
    }

    public function text(string $text): void
    {
        $this->literal(Escaper::text($text));
    }

    public function raw(Raw $raw): void
    {
        $this->literal((string)$raw);
    }

    public function slotEnter(Slot $slot, string $slotPath): void
    {
        $this->slotStack[] = ['slot' => $slot, 'slotPath' => $slotPath];

        if ($this->enterValueSlot($slot, $slotPath)) {
            return;
        }

        $this->enterScopeSlot($slot, $slotPath);
    }

    public function slotBranch(string|int $label): void
    {
        $slot = $this->currentContext()['slot'];

        if ($slot->kind !== SlotKind::If) {
            throw new LogicException("unexpected branch event for slot kind '{$slot->kind->name}'.");
        }

        if ($label === 1) {
            $this->statement('} else {');
        }
    }

    public function slotLeave(Slot $slot, string $slotPath): void
    {
        switch ($slot->kind) {
            case SlotKind::Value:
            case SlotKind::Raw:
                break;
            case SlotKind::Child:
                array_pop($this->dataStack);

                break;
            case SlotKind::Each:
                $this->statement('}');
                array_pop($this->dataStack);

                break;
            case SlotKind::If:
                $this->statement('}');

                break;
        }

        array_pop($this->slotStack);
    }

    private function enterValueSlot(Slot $slot, string $slotPath): bool
    {
        switch ($slot->kind) {
            case SlotKind::Value:
                $this->expression($this->valueSource('text', $slot, $this->data(), $slotPath));

                return true;
            case SlotKind::Raw:
                $this->expression($this->valueSource('raw', $slot, $this->data(), $slotPath));

                return true;
            default:
                return false;
        }
    }

    private function enterScopeSlot(Slot $slot, string $slotPath): void
    {
        switch ($slot->kind) {
            case SlotKind::Child:
                $this->enterChild($slot, $slotPath);

                return;
            case SlotKind::Each:
                $this->enterEach($slot, $slotPath);

                return;
            case SlotKind::If:
                $this->enterIf($slot, $slotPath);

                return;
            default:
                throw new LogicException("slot kind '{$slot->kind->name}' is not supported in child position.");
        }
    }

    /**
     * Open a child slot: bind its nested data scope.
     */
    protected function enterChild(Slot $slot, string $slotPath): void
    {
        $childVar = $this->childVar();
        $this->statement($childVar . ' = ' . $this->childSource($slot, $this->data(), $slotPath) . ';');
        $this->dataStack[] = $childVar;
    }

    /**
     * Open a list slot: iterate it and bind the scope of one item.
     */
    protected function enterEach(Slot $slot, string $slotPath): void
    {
        $itemVar = $this->itemVar();
        $this->statement('foreach (' . $this->itemsSource($slot, $this->data(), $slotPath) . ' as ' . $itemVar . ') {');
        $this->dataStack[] = $this->eachScope($this->childVar(), $itemVar, $slotPath . '[]', $slot->shape);
    }

    /**
     * Open an if slot.
     */
    protected function enterIf(Slot $slot, string $slotPath): void
    {
        $this->statement('if ((bool)' . $this->conditionExpr($slot, $this->data()) . ') {');
    }

    /**
     * Bind the data scope of one list item and return the variable holding it.
     *
     * The item shape decides how much of an item the scope needs: a shape that
     * renders one key binds a scalar item to that key, so a list of strings
     * renders without a one-key map per item, while any other shape takes the
     * item itself as the scope and names the keys it reads when the item is
     * not one.
     */
    protected function eachScope(string $childVar, string $itemVar, string $scopePath, ?ShapeContract $shape): string
    {
        $manifest = RootSlots::itemManifest($shape);
        $key = RootSlots::itemKey($manifest);

        if ($key === null) {
            $hint = RootSlots::itemHint($manifest);
        } else {
            // An array item is a scope of its own, so both forms render.
            $itemVar = '(is_array(' . $itemVar . ') ? ' . $itemVar . ' : [' . var_export($key, true) . ' => ' . $itemVar . '])';
            $hint = '';
        }

        $this->statement($childVar . ' = ' . $this->scopeSource($itemVar, $scopePath, $hint) . ';');

        return $childVar;
    }

    /**
     * The next unique data scope variable.
     */
    protected function childVar(): string
    {
        return '$v' . (++$this->scope);
    }

    /**
     * The next unique list item variable.
     */
    protected function itemVar(): string
    {
        return '$item' . (++$this->scope);
    }

    /**
     * Return the current slot stack context.
     *
     * @return array{slot: Slot, slotPath: string}
     */
    private function currentContext(): array
    {
        $context = end($this->slotStack);
        if ($context === false) {
            throw new LogicException('slot event outside of a slot.');
        }

        return $context;
    }

    protected function data(): string
    {
        $data = end($this->dataStack);
        if ($data === false) {
            throw new LogicException('missing data scope.');
        }

        return $data;
    }

    /**
     * Expression writing the nested data scope of a slot.
     *
     * The hint is a compiled constant appended to the message of a rejected
     * value, so it never costs anything on the path where the value is a scope.
     */
    protected function scopeSource(string $value, string $scopePath, string $hint = ''): string
    {
        return '\Pure\Compile\Internal\SlotRuntime::scope(' . $value . ', ' . var_export($scopePath, true)
            . ($hint === '' ? '' : ', ' . var_export($hint, true)) . ')';
    }

    private function conditionExpr(Slot $slot, string $dataVar): string
    {
        return '(' . $dataVar . '[' . var_export($slot->name, true) . '] ?? ' . var_export($slot->default, true) . ')';
    }

    private function valueAccess(Slot $slot, string $dataVar, string $slotPath, bool $nullThrows = false): string
    {
        $key = var_export($slot->name, true);
        $path = var_export($slotPath, true);

        if (!$slot->required) {
            return '(' . $dataVar . '[' . $key . '] ?? ' . var_export($slot->default, true) . ')';
        }

        if ($nullThrows) {
            // An explicit null fails a required text or raw slot. `??` catches
            // both the null and the missing case, and forPath tells them apart.
            return '(' . $dataVar . '[' . $key . '] ?? throw \Pure\Core\MissingSlotException::forPath(' . $path . ', ' . $dataVar . '))';
        }

        return '(\array_key_exists(' . $key . ', ' . $dataVar . ') ? ' . $dataVar . '[' . $key . '] : throw \Pure\Core\MissingSlotException::forPath(' . $path . ', ' . $dataVar . '))';
    }

    /**
     * Expression rendering one text or raw slot value.
     */
    protected function valueSource(string $kind, Slot $slot, string $dataVar, string $slotPath): string
    {
        $access = $this->valueAccess($slot, $dataVar, $slotPath, true);

        if ($kind === 'text') {
            // Scalars (the common case) are escaped inline with the shared
            // Escaper constants. Everything else keeps the SlotRuntime::text()
            // call, so an optional null stays silent, Stringable values are
            // coerced and arrays keep the InvalidArgumentException with the
            // slot path. A required slot never reaches it with null: valueAccess
            // rejects that above.
            return '(is_scalar($text = ' . $access . ')'
                . ' ? htmlspecialchars((string)$text, \Pure\Core\Escaper::FLAGS, \Pure\Core\Escaper::ENCODING, false)'
                . ' : \Pure\Compile\Internal\SlotRuntime::text($text, ' . var_export($slotPath, true) . '))';
        }

        return '\Pure\Compile\Internal\SlotRuntime::' . $kind . '(' . $access . ', ' . var_export($slotPath, true) . ')';
    }

    /**
     * Expression rendering one attribute slot as its ` name="value"` chunk.
     */
    protected function attrSource(string $key, Slot $slot, string $dataVar, string $slotPath): string
    {
        return '\Pure\Compile\Internal\SlotRuntime::attrOpen(' . var_export($key, true) . ', '
            . $this->valueAccess($slot, $dataVar, $slotPath) . ', '
            . var_export($slotPath, true) . ')';
    }

    /**
     * Expression producing the nested data scope of a child slot.
     */
    protected function childSource(Slot $slot, string $dataVar, string $slotPath): string
    {
        return $this->scopeSource($this->valueAccess($slot, $dataVar, $slotPath), $slotPath);
    }

    /**
     * Expression producing the iterable of an each slot.
     */
    protected function itemsSource(Slot $slot, string $dataVar, string $slotPath): string
    {
        return '\Pure\Compile\Internal\SlotRuntime::items(' . $this->valueAccess($slot, $dataVar, $slotPath) . ', ' . var_export($slotPath, true) . ')';
    }

    /**
     * Whether the subtree contains any slot.
     *
     * Memoized per tag: without the cache this walks every descendant again for
     * each ancestor and compile time turns quadratic (depth 800: ~32 ms vs
     * ~1.5 ms memoized).
     */
    private function hasSlots(Tag $tag): bool
    {
        $cache = $this->slotCache;

        if (isset($cache[$tag])) {
            return $cache[$tag];
        }

        $export = $tag->export();
        $hasSlots = false;

        foreach ($export['attrs'] as $value) {
            if ($value instanceof Slot) {
                $hasSlots = true;

                break;
            }
        }

        if (!$hasSlots) {
            foreach ($export['children'] as $child) {
                if ($child instanceof Slot || ($child instanceof Tag && $this->hasSlots($child))) {
                    $hasSlots = true;

                    break;
                }
            }
        }

        $cache[$tag] = $hasSlots;

        return $hasSlots;
    }

    protected function literal(string $text): void
    {
        $this->literal .= $text;
    }

    protected function expression(string $expression): void
    {
        $this->flushLiteral();
        $this->emitExpression($expression);
    }

    protected function statement(string $statement): void
    {
        $this->flushLiteral();
        $this->emitStatement($statement);
    }

    protected function flushLiteral(): void
    {
        if ($this->literal === '') {
            return;
        }

        $this->emitLiteral($this->literal);
        $this->literal = '';
    }

    /**
     * Write the pending static markup.
     */
    abstract protected function emitLiteral(string $text): void;

    /**
     * Write one echo of an expression.
     */
    abstract protected function emitExpression(string $expression): void;

    /**
     * Write one control-flow or assignment statement.
     */
    abstract protected function emitStatement(string $statement): void;
}
