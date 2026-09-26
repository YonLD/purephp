<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

use Pure\Core\ShapeContract;
use Pure\Core\Slot;
use Pure\Core\SlotKind;
use Pure\Core\Tag;

/**
 * The data keys of the root scope of a shape tree.
 *
 * The manifest is what a compiled renderer knows about its bindings: the
 * development guard reports data keys outside it, and `pure check` compares it
 * against the component function. Only the root scope is collected — a child or
 * list slot introduces a nested scope whose keys belong to the parent
 * component — while `Slot::if` branches stay in the current scope and are
 * walked.
 *
 * @internal
 */
final class RootSlots
{
    private function __construct()
    {
    }

    /**
     * The root slot names, in first-seen order.
     *
     * @param Tag $tree The shape tree.
     * @return list<string>
     */
    public static function of(Tag $tree): array
    {
        return array_keys(self::manifest($tree));
    }

    /**
     * The root slot contract: per slot name, whether any occurrence is required
     * and which kinds use it.
     *
     * @param Tag $tree The shape tree.
     * @return array<string, array{required: bool, kinds: array<string, true>}>
     */
    public static function manifest(Tag $tree): array
    {
        $slots = [];
        self::collect($tree, $slots);

        return $slots;
    }

    /**
     * @param array<string, array{required: bool, kinds: array<string, true>}> $slots
     */
    private static function collect(Tag $tag, array &$slots): void
    {
        $export = $tag->export();

        foreach ($export['attrs'] as $value) {
            if ($value instanceof Slot) {
                self::add($slots, $value);
            }
        }

        if ($export['selfClose']) {
            return;
        }

        foreach ($export['children'] as $child) {
            if ($child instanceof Tag) {
                self::collect($child, $slots);

                continue;
            }

            if (!$child instanceof Slot) {
                continue;
            }

            self::add($slots, $child);

            if ($child->kind === SlotKind::Child || $child->kind === SlotKind::Each) {
                continue;
            }

            if ($child->kind === SlotKind::If) {
                if ($child->shape !== null) {
                    self::collect($child->shape->tree(), $slots);
                }

                if ($child->else !== null) {
                    self::collect($child->else->tree(), $slots);
                }
            }
        }
    }

    /**
     * The merged manifest of the item shapes of a list slot: the keys each
     * item scope reads, across every `Slot::each($slot, ...)` in the tree.
     *
     * An empty result means no list slot of that name holds a shape that reads
     * keys of its own, so `pure check` can tell a text slot from a list slot
     * whose items are static markup.
     *
     * @param Tag $tree The shape tree.
     * @param string $slot The list slot name.
     * @return array<string, array{required: bool, kinds: array<string, true>}>
     */
    public static function itemSlots(Tag $tree, string $slot): array
    {
        $items = [];

        foreach (self::itemShapes($tree, $slot) as $shape) {
            foreach (self::manifest($shape->tree()) as $name => $info) {
                $entry = $items[$name] ?? ['required' => false, 'kinds' => []];
                $entry['required'] = $entry['required'] || $info['required'];

                foreach (array_keys($info['kinds']) as $kind) {
                    $entry['kinds'][$kind] = true;
                }

                $items[$name] = $entry;
            }
        }

        return $items;
    }

    /**
     * The item shapes of a list slot, in tree order.
     *
     * @param Tag $tree The shape tree.
     * @param string $slot The list slot name.
     * @return list<ShapeContract>
     */
    private static function itemShapes(Tag $tree, string $slot): array
    {
        $shapes = [];
        $export = $tree->export();

        if ($export['selfClose']) {
            return $shapes;
        }

        foreach ($export['children'] as $child) {
            if ($child instanceof Tag) {
                foreach (self::itemShapes($child, $slot) as $shape) {
                    $shapes[] = $shape;
                }

                continue;
            }

            if ($child instanceof Slot && $child->kind === SlotKind::Each && $child->name === $slot && $child->shape !== null) {
                $shapes[] = $child->shape;
            }
        }

        return $shapes;
    }

    /**
     * The item scope manifest of a list slot: what each item of that slot has to
     * supply.
     *
     * Every consumer of an item shape goes through this one analysis, so asking
     * several questions of the same shape costs a single walk of it. A list slot
     * with no shape has no item scope, which is an empty manifest; the walk
     * rejects the missing shape right after, so no renderer is built from it.
     *
     * @param ShapeContract|null $shape The item shape of a list slot.
     * @return array<string, array{required: bool, kinds: array<string, true>}>
     */
    public static function itemManifest(?ShapeContract $shape): array
    {
        return $shape === null ? [] : self::manifest($shape->tree());
    }

    /**
     * The single key an item shape renders, when one value stands for a whole
     * item scope.
     *
     * An item shape that reads exactly one key as a value or raw slot binds that
     * key directly, so a scalar item can stand in for it: a list of strings
     * renders a list of strings, and the caller does not wrap every item in a
     * one-key map. The name is null when the shape reads several keys, reads
     * its only key as a nested scope (a child or list slot needs a real array or
     * iterable), or reads no key at all — there an item is a scope of its own
     * and has to be an array.
     *
     * @param array<string, array{required: bool, kinds: array<string, true>}> $manifest The item scope manifest.
     * @return ?string The key a scalar item binds, or null when it binds none.
     */
    public static function itemKey(array $manifest): ?string
    {
        if (count($manifest) !== 1) {
            return null;
        }

        $name = array_key_first($manifest);
        $kinds = $manifest[$name]['kinds'];

        // Reading the key as a nested scope as well means the item has to stay a
        // scope of its own, so a scalar cannot stand in for it even though the
        // shape renders that same key as a value.
        if (isset($kinds[SlotKind::Child->name]) || isset($kinds[SlotKind::Each->name])) {
            return null;
        }

        // A condition is a truthiness read, not a rendered value: binding a
        // scalar to it would pick a branch per item, which the shape never asked
        // for.
        return isset($kinds[SlotKind::Value->name]) || isset($kinds[SlotKind::Raw->name]) ? $name : null;
    }

    /**
     * The message suffix naming the keys an item shape reads, for a list item
     * that is not a scope.
     *
     * The suffix is a compiled constant, so it costs nothing on the path where
     * the item is a scope: only the throw in SlotRuntime::scope() reads it.
     *
     * @param array<string, array{required: bool, kinds: array<string, true>}> $manifest The item scope manifest.
     * @return string The suffix appended to the message of a rejected item.
     */
    public static function itemHint(array $manifest): string
    {
        $slots = array_keys($manifest);

        if ($slots === []) {
            return ' The item shape of this slot reads no slots, so each item must be an empty array.';
        }

        $quoted = array_map(static fn (string $name): string => "'{$name}'", $slots);
        $last = array_pop($quoted);
        $list = implode(', ', $quoted) . ($quoted === [] ? '' : ' and ') . $last;

        return " The item shape of this slot reads {$list}, so each item must be an array.";
    }

    /**
     * @param array<string, array{required: bool, kinds: array<string, true>}> $slots
     */
    private static function add(array &$slots, Slot $slot): void
    {
        $entry = $slots[$slot->name] ?? ['required' => false, 'kinds' => []];
        $entry['required'] = $entry['required'] || $slot->required;
        $entry['kinds'][$slot->kind->name] = true;

        $slots[$slot->name] = $entry;
    }
}
