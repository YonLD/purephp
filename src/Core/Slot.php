<?php

declare(strict_types=1);

namespace Pure\Core;

use InvalidArgumentException;
use LogicException;

/**
 * Placeholder for data bound at render time by a compiled shape.
 *
 * Slots live inside a shape tree built for Pure\Compile\Compile::shape() and
 * cannot be rendered by Tag::render() directly. Nested shapes are typed against
 * ShapeContract so that Pure\Core does not depend on Pure\Compile: a bare tag
 * tree satisfies the contract (a tag is already a data-free tree), so
 * `Slot::each('items', li(Slot::value('value')))` needs no wrapper, while a
 * memoized Pure\Compile\Shape is still accepted.
 */
final class Slot
{
    private function __construct(
        public readonly SlotKind $kind,
        public readonly string $name,
        public readonly ?ShapeContract $shape,
        public readonly bool $required,
        public readonly mixed $default,
        public readonly ?ShapeContract $else = null,
    ) {
    }

    /**
     * Value slot: the bound value is interpreted by position.
     *
     * In child position the value is coerced to string and escaped (true→"1");
     * a required slot rejects an explicit null with MissingSlotException, while
     * an optional slot renders it as an empty string. In attribute position the
     * value follows Tag::setAttr() semantics (null/false omitted,
     * true→`name="name"`). The slot name is the data key holding the value.
     *
     * @param string $name The slot/data key.
     * @return self
     */
    public static function value(string $name): self
    {
        return new self(SlotKind::Value, $name, null, true, null);
    }

    /**
     * Raw content slot: the value is emitted verbatim, without escaping.
     *
     * The bound value may be a string, any Stringable, or an iterable of such
     * values: each is stringified and the results are concatenated in order.
     * Like a value slot, a required raw slot rejects an explicit null; an
     * optional one renders it as an empty string.
     *
     * Choose between the two list slots by when the markup exists: raw()
     * concatenates markup that is already rendered; each() is data-driven and
     * renders every item through its own shape.
     *
     * @param string $name The slot name.
     * @return self
     */
    public static function raw(string $name): self
    {
        return new self(SlotKind::Raw, $name, null, true, null);
    }

    /**
     * Child component slot: the nested data scope is `$data[$name]`.
     *
     * @param string $name The slot name.
     * @param ShapeContract $shape The shape or bare tag tree rendering this slot's content.
     * @return self
     */
    public static function child(string $name, ShapeContract $shape): self
    {
        return new self(SlotKind::Child, $name, $shape, true, null);
    }

    /**
     * List slot: the value is an iterable of items, each rendered by $shape
     * and each being the nested scope of its item.
     *
     * When the item shape renders exactly one slot, an item may be that slot's
     * value instead of a scope, so a list of strings needs no wrapping; an item
     * that is an array is still a scope of its own, and both forms render. An
     * item shape that reads several slots, reads its one slot as a nested
     * child or list slot, or reads no slot at all takes an array per item.
     *
     * If the items are already-rendered markup (a Raw, or a list of them), use a
     * raw() slot and pass the list directly instead: each() is for data you
     * still need to render item by item.
     *
     * @param string $name The slot name.
     * @param ShapeContract $shape The shape or bare tag tree rendering each item.
     * @return self
     */
    public static function each(string $name, ShapeContract $shape): self
    {
        return new self(SlotKind::Each, $name, $shape, true, null);
    }

    /**
     * Conditional slot: renders $then when `$data[$name]` is truthy, otherwise
     * $else (or nothing). A missing key is false and never throws; then/else
     * branches share the current data scope.
     *
     * @param string $name The slot name.
     * @param ShapeContract $then The shape or bare tag tree of the truthy branch.
     * @param ShapeContract|null $else The shape or bare tag tree of the falsy branch.
     * @return self
     */
    public static function if(string $name, ShapeContract $then, ?ShapeContract $else = null): self
    {
        return new self(SlotKind::If, $name, $then, false, false, $else);
    }

    /**
     * Mark the slot as optional.
     *
     * @param bool $required Whether the slot is required (default true).
     * @return self
     */
    public function required(bool $required = true): self
    {
        if ($this->kind === SlotKind::If) {
            throw new LogicException("slot '{$this->name}' is a condition slot; required() does not apply.");
        }

        return new self($this->kind, $this->name, $this->shape, $required, $this->default, $this->else);
    }

    /**
     * Provide a fallback value, making the slot optional.
     *
     * Defaults are inlined into the compiled renderer, so they must be value
     * types: null, a scalar, or an array of value types.
     *
     * @param mixed $value The default value.
     * @return self
     */
    public function default(mixed $value): self
    {
        if ($this->kind === SlotKind::If) {
            throw new LogicException("slot '{$this->name}' is a condition slot; default() does not apply.");
        }

        if (!self::isValueType($value)) {
            throw new InvalidArgumentException(
                "slot '{$this->name}' default must be null, a scalar or an array of value types, "
                . get_debug_type($value) . ' given.'
            );
        }

        return new self($this->kind, $this->name, $this->shape, false, $value, $this->else);
    }

    private static function isValueType(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::isValueType($item)) {
                    return false;
                }
            }

            return true;
        }

        return is_scalar($value) || $value === null;
    }
}
