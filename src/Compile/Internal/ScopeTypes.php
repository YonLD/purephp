<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

use LogicException;
use Pure\Core\ShapeContract;
use Pure\Core\Slot;
use Pure\Core\SlotKind;
use Pure\Core\Tag;

/**
 * Derives the `@var` annotations of a plain view from the shape tree.
 *
 * A plain view reads root slots as locals (the loader extracts the data array)
 * and every nested slot as an offset of the array its root slot holds, so the
 * compiler can tell static analyzers what the view expects: text and attribute
 * slots accept what PHP stringifies (`scalar|null|\Stringable`), raw slots also
 * accept an iterable of those (a plain view joins it), condition slots are
 * `mixed` (they are read as `(bool)`), and child and each slots become array
 * shapes and iterables of them.
 *
 * @internal
 */
final class ScopeTypes
{
    /**
     * The value type of a text or attribute slot: what SlotRuntime and the
     * inlined htmlspecialchars() calls of a plain view accept. The null keeps
     * `$x ?? default` reads valid for optional slots.
     */
    private const VALUE = 'scalar|null|\\Stringable';

    /**
     * The value type of a raw slot: a stringable, or an iterable of stringables
     * that the renderer joins.
     */
    private const RAW = 'iterable<array-key, scalar|null|\\Stringable>|scalar|null|\\Stringable';

    /**
     * The value type of a condition slot: `(bool)` accepts anything, and the
     * generated `?? false` read stays valid because mixed includes null.
     */
    private const CONDITION = 'mixed';

    /**
     * The data keys of the root scope: locals declared as variables, and the
     * keys read from the loader's `$data` array.
     *
     * @var array<string, array{types: array<string, true>, optional: bool}>
     */
    private array $locals = [];

    /** @var array<string, array{types: array<string, true>, optional: bool}> */
    private array $data = [];

    /** @var array<string, array{types: array<string, true>, optional: bool}> */
    private array $root = [];

    /**
     * Whether the view reads the loader's `$data` array: an odd slot name is
     * read as an offset of it.
     */
    private bool $readsData = false;

    private function __construct()
    {
    }

    /**
     * The `@var` docblock of a plain view, or an empty string when the tree
     * has no root slots.
     *
     * @param Tag $tree The shape tree to describe.
     * @return string The docblock source, without a trailing newline.
     */
    public static function docblock(Tag $tree): string
    {
        $types = new self();
        $types->collect($tree, $types->root);

        foreach ($types->root as $name => $entry) {
            if (PlainGenerator::local($name) === null) {
                $types->merge($types->data, $name, $entry);
                $types->readsData = true;
            } else {
                $types->merge($types->locals, $name, $entry);
            }
        }

        $lines = [];

        foreach ($types->locals as $name => $entry) {
            $lines[] = ' * @var ' . $types->localType($entry) . ' $' . $name;
        }

        if ($types->readsData) {
            $lines[] = ' * @var ' . $types->dataType() . ' $data';
        }

        if ($lines === []) {
            return '';
        }

        return "/**\n" . implode("\n", $lines) . "\n */";
    }

    /**
     * Collect the data keys of one scope from the tags and slots of a shape.
     *
     * @param array<string, array{types: array<string, true>, optional: bool}> $scope
     */
    private function collect(Tag $tag, array &$scope): void
    {
        $export = $tag->export();

        foreach ($export['attrs'] as $value) {
            if ($value instanceof Slot) {
                $this->addSlot($scope, $value);
            }
        }

        if ($export['selfClose']) {
            return;
        }

        foreach ($export['children'] as $child) {
            if ($child instanceof Tag) {
                $this->collect($child, $scope);

                continue;
            }

            if ($child instanceof Slot) {
                $this->addSlot($scope, $child);
            }
        }
    }

    /**
     * Add the data key one slot reads to a scope.
     *
     * @param array<string, array{types: array<string, true>, optional: bool}> $scope
     */
    private function addSlot(array &$scope, Slot $slot): void
    {
        if ($slot->kind === SlotKind::If) {
            // A condition is always read with `??`, so the key may be absent;
            // merging with a required occurrence makes it required again.
            $this->merge($scope, $slot->name, $this->entry(self::CONDITION, true));

            // Both branches render into the current scope.
            if ($slot->shape !== null) {
                $this->collect($slot->shape->tree(), $scope);
            }

            if ($slot->else !== null) {
                $this->collect($slot->else->tree(), $scope);
            }

            return;
        }

        $this->merge($scope, $slot->name, $this->entry($this->slotType($slot), $this->isOptionalContainer($slot)));
    }

    /**
     * The scope type of a slot that reads its value directly.
     */
    private function slotType(Slot $slot): string
    {
        return match ($slot->kind) {
            SlotKind::Value => self::VALUE,
            SlotKind::Raw => self::RAW,
            SlotKind::Child => $this->shapeType($slot->shape),
            SlotKind::Each => 'iterable<array-key, ' . $this->itemType($slot->shape) . '>',
            default => throw new LogicException("slot kind '{$slot->kind->name}' has no scope type."),
        };
    }

    /**
     * The scope type of one item of a list slot.
     *
     * An item shape that renders one key also accepts a scalar, which stands in
     * for the whole item scope, so the plain view binds it the same way the
     * runtime does.
     */
    private function itemType(?ShapeContract $shape): string
    {
        $type = $this->shapeType($shape);

        // A scalar item is bound to the one key the shape renders, and an array
        // item is always a scope, so `scalar|null|\Stringable` is exactly what
        // the generated `is_array()` test admits.
        return RootSlots::itemKey(RootSlots::itemManifest($shape)) === null
            ? $type
            : $type . '|' . self::VALUE;
    }

    /**
     * The scope type of a child shape.
     */
    private function shapeType(?ShapeContract $shape): string
    {
        if ($shape === null) {
            return 'array<array-key, mixed>';
        }

        $scope = [];
        $this->collect($shape->tree(), $scope);

        return $this->render($scope);
    }

    /**
     * Whether a container slot is optional in the generated reads.
     */
    private function isOptionalContainer(Slot $slot): bool
    {
        return !$slot->required
            && ($slot->kind === SlotKind::Child
                || $slot->kind === SlotKind::Each);
    }

    /**
     * The `@var` type of a root local: an optional container may be missing,
     * and the generated read falls back to its default.
     *
     * @param array{types: array<string, true>, optional: bool} $entry
     */
    private function localType(array $entry): string
    {
        $type = implode('|', $this->types($entry));

        if ($entry['optional'] && $type !== 'mixed') {
            $type .= '|null';
        }

        return $type;
    }

    /**
     * The type of the loader's `$data` array: every root key when the shape
     * has any, otherwise a loose array, because only odd slot names read it.
     */
    private function dataType(): string
    {
        if ($this->root === []) {
            return 'array<array-key, mixed>';
        }

        return $this->render($this->root);
    }

    /**
     * @param string $type
     * @return array{types: array<string, true>, optional: bool}
     */
    private function entry(string $type, bool $optional): array
    {
        return ['types' => [$type => true], 'optional' => $optional];
    }

    /**
     * Merge one key into a scope: types are unioned, and a key stays optional
     * only while every occurrence of it is optional.
     *
     * @param array<string, array{types: array<string, true>, optional: bool}> $scope
     * @param array{types: array<string, true>, optional: bool} $entry
     */
    private function merge(array &$scope, string $name, array $entry): void
    {
        if (!isset($scope[$name])) {
            $scope[$name] = $entry;

            return;
        }

        $scope[$name]['types'] += $entry['types'];
        $scope[$name]['optional'] = $scope[$name]['optional'] && $entry['optional'];
    }

    /**
     * Render one scope as a phpdoc array shape, or a loose array when the
     * shape has no keys.
     *
     * @param array<string, array{types: array<string, true>, optional: bool}> $scope
     */
    private function render(array $scope): string
    {
        if ($scope === []) {
            return 'array<array-key, mixed>';
        }

        $parts = [];
        foreach ($scope as $name => $entry) {
            $parts[] = self::key($name) . ($entry['optional'] ? '?' : '')
                . ': ' . implode('|', $this->types($entry));
        }

        return 'array{' . implode(', ', $parts) . '}';
    }

    /**
     * The distinct types of one key. `mixed` absorbs the other members of a
     * union in analyzers, so a condition key that is also read as a value
     * drops it: the value type keeps the generated reads checked.
     *
     * @param array{types: array<string, true>, optional: bool} $entry
     * @return list<string>
     */
    private function types(array $entry): array
    {
        $types = array_keys($entry['types']);

        if (count($types) > 1) {
            $types = array_values(array_filter(
                $types,
                static fn (string $type): bool => $type !== 'mixed'
            ));
        }

        return $types;
    }

    /**
     * A phpdoc array shape key: bare when it is an identifier, quoted
     * otherwise.
     */
    private static function key(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1) {
            return $name;
        }

        return var_export($name, true);
    }
}
