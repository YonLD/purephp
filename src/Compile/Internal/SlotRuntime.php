<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

use InvalidArgumentException;
use Pure\Core\Escaper;
use Stringable;

/**
 * Runtime value coercion used by generated renderers.
 *
 * @internal
 */
final class SlotRuntime
{
    /**
     * Coerce a slot value to escaped text content.
     *
     * Generated renderers inline this call for scalar values (see
     * CodeGenerator::valueExpr()) and only fall back here for null, Stringable
     * and invalid values, so the documented empty-null behaviour and the
     * path-bearing InvalidArgumentException stay in one place. Byte-identity
     * with Escaper::text() is locked by
     * CompileTest::testLiteralAndSlotEscapingStayByteIdentical.
     *
     * @param mixed $value The slot value.
     * @param string $path The slot path for error messages.
     * @return string The escaped text content.
     */
    public static function text(mixed $value, string $path): string
    {
        if (is_scalar($value)) {
            // Scalars are the common case for the accessors in template
            // artifacts, where this method is the only escaping entry point.
            return htmlspecialchars((string)$value, Escaper::FLAGS, Escaper::ENCODING, false);
        }

        return htmlspecialchars(self::stringify($value, $path), Escaper::FLAGS, Escaper::ENCODING, false);
    }

    /**
     * Coerce a raw slot value to verbatim output.
     *
     * The common value is a scalar (or null) and is cast as-is, so it never
     * pays for the iterable check. An iterable of such values (a string, a Raw,
     * a Stringable, or null, per element) is stringified element by element and
     * concatenated, so a rendered list of component markup can be passed
     * directly without an intermediate implode(). A non-stringable element
     * reports its offset: slot 'navs[2]' must be stringable.
     *
     * @param mixed $value The slot value.
     * @param string $path The slot path for error messages.
     * @return string The verbatim output.
     */
    public static function raw(mixed $value, string $path): string
    {
        if (is_null($value) || is_scalar($value)) {
            return (string)$value;
        }

        if (is_iterable($value)) {
            $out = '';
            foreach ($value as $index => $item) {
                $out .= self::stringify($item, $path . '[' . $index . ']');
            }

            return $out;
        }

        return self::stringify($value, $path);
    }

    /**
     * Build one `name="value"` attribute chunk, omitting it for null values.
     *
     * Mirrors Tag::setAttr(): a null value leaves the attribute unset, `false`
     * omits it and `true` renders the name as its own value (`disabled`).
     *
     * The scalar branch is deliberately separate: attribute slots inside
     * `Slot::each()` loops run once per item, and merging it into stringify()
     * costs ~8% of a list-heavy page's render (measured on the 604-element
     * benchmark page).
     *
     * @param string $name The attribute name.
     * @param mixed $value The attribute value.
     * @param string $path The slot path for error messages.
     * @return string The serialized attribute chunk, or empty string if null.
     */
    public static function attrOpen(string $name, mixed $value, string $path): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? Escaper::attribute($name, $name) : '';
        }

        if (is_scalar($value)) {
            return Escaper::attribute($name, (string)$value);
        }

        return Escaper::attribute($name, self::stringify($value, $path));
    }

    /**
     * Ensure a child component value is an array usable as a nested data scope.
     *
     * The hint is a compiled constant naming what the scope expects, appended to
     * the message of a rejected value; a generated renderer passes it for the
     * items of a list slot, whose shape decides whether a value can stand in
     * for the scope at all.
     *
     * @param mixed $value The slot value.
     * @param string $path The slot path for error messages.
     * @param string $hint The message suffix for a value that is not a scope.
     * @return array<array-key, mixed>
     * @throws InvalidArgumentException When the value is not an array.
     */
    public static function scope(mixed $value, string $path, string $hint = ''): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                "slot '{$path}' must be an array, " . get_debug_type($value) . ' given.' . $hint
            );
        }

        return $value;
    }

    /**
     * Ensure a list slot value is iterable.
     *
     * @param mixed $value The slot value.
     * @param string $path The slot path for error messages.
     * @return iterable<array-key, mixed>
     * @throws InvalidArgumentException When the value is not iterable.
     */
    public static function items(mixed $value, string $path): iterable
    {
        if (!is_iterable($value)) {
            throw new InvalidArgumentException("slot '{$path}' must be iterable, " . get_debug_type($value) . ' given.');
        }

        return $value;
    }

    private static function stringify(mixed $value, string $path): string
    {
        if (is_null($value) || is_scalar($value)) {
            return (string)$value;
        }

        if ($value instanceof Stringable) {
            return (string)$value;
        }

        throw new InvalidArgumentException("slot '{$path}' must be stringable, " . get_debug_type($value) . ' given.');
    }
}
