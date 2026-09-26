<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

use Pure\Core\Escaper;
use Pure\Core\MissingSlotException;

/**
 * Data accessors used by template artifacts.
 *
 * A template artifact reads its slots through these helpers, so the generated
 * template stays readable (`TemplateRuntime::text($v, 'title')`) while keeping
 * the flat renderer's semantics: a required slot throws MissingSlotException
 * when the data does not provide it (an explicit null fails a text or raw slot,
 * while an attribute slot keeps omitting itself), an optional slot falls back
 * to the compiled default, and values are coerced by SlotRuntime, so both
 * sources render byte-identical output.
 *
 * The `$path` argument is only passed when the slot path differs from the slot
 * key (slots inside `Slot::each()` and `Slot::child()` scopes); it is the path
 * used in error messages. The default is detected by the argument count, so
 * `default: null` and "no default" stay distinguishable.
 *
 * @internal
 */
final class TemplateRuntime
{
    /**
     * Escape a text slot value.
     *
     * @param array<array-key, mixed> $scope The data scope to read from.
     * @param string $key The slot key.
     * @param ?string $path The slot path for error messages, when it differs from the key.
     * @param mixed $default The compiled default of an optional slot.
     * @return string The escaped text content.
     */
    public static function text(array $scope, string $key, ?string $path = null, mixed $default = null): string
    {
        $value = $scope[$key]
            ?? self::fallback($scope, $key, $path, $default, func_num_args() >= 4, true);

        // Scalars (the common case) escape inline with the shared constants,
        // exactly like the flat renderer's generated expression; everything
        // else keeps the SlotRuntime call for the null/Stringable/invalid
        // semantics and the path-bearing exception.
        if (is_scalar($value)) {
            return htmlspecialchars((string)$value, Escaper::FLAGS, Escaper::ENCODING, false);
        }

        return SlotRuntime::text($value, $path ?? $key);
    }

    /**
     * Coerce a raw slot value.
     *
     * @param array<array-key, mixed> $scope The data scope to read from.
     * @param string $key The slot key.
     * @param ?string $path The slot path for error messages, when it differs from the key.
     * @param mixed $default The compiled default of an optional slot.
     * @return string The verbatim output.
     */
    public static function raw(array $scope, string $key, ?string $path = null, mixed $default = null): string
    {
        $value = $scope[$key]
            ?? self::fallback($scope, $key, $path, $default, func_num_args() >= 4, true);

        return is_scalar($value) ? (string)$value : SlotRuntime::raw($value, $path ?? $key);
    }

    /**
     * Build one `name="value"` attribute chunk, omitting it for null values.
     *
     * @param array<array-key, mixed> $scope The data scope to read from.
     * @param string $key The slot key.
     * @param ?string $name The attribute name, when it differs from the slot key.
     * @param ?string $path The slot path for error messages, when it differs from the key.
     * @param mixed $default The compiled default of an optional slot.
     * @return string The serialized attribute chunk, or empty string if null.
     */
    public static function attr(array $scope, string $key, ?string $name = null, ?string $path = null, mixed $default = null): string
    {
        $value = $scope[$key]
            ?? self::fallback($scope, $key, $path, $default, func_num_args() >= 5, false);

        $name ??= $key;

        // The scalar branch builds the chunk inline; the order of the checks
        // and the escaping flags mirror SlotRuntime::attrOpen() so the bytes
        // stay identical to the flat renderer.
        if (is_string($value)) {
            return ' ' . $name . '="' . htmlspecialchars($value, Escaper::FLAGS, Escaper::ENCODING) . '"';
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? Escaper::attribute($name, $name) : '';
        }

        if (is_int($value) || is_float($value)) {
            return ' ' . $name . '="' . htmlspecialchars((string)$value, Escaper::FLAGS, Escaper::ENCODING) . '"';
        }

        return SlotRuntime::attrOpen($name, $value, $path ?? $key);
    }

    /**
     * Read a child component value as a nested data scope.
     *
     * @param array<array-key, mixed> $scope The data scope to read from.
     * @param string $key The slot key.
     * @param ?string $path The slot path for error messages, when it differs from the key.
     * @param mixed $default The compiled default of an optional slot.
     * @return array<array-key, mixed>
     */
    public static function child(array $scope, string $key, ?string $path = null, mixed $default = null): array
    {
        $value = $scope[$key]
            ?? self::fallback($scope, $key, $path, $default, func_num_args() >= 4, false);

        return is_array($value) ? $value : SlotRuntime::scope($value, $path ?? $key);
    }

    /**
     * Read a list slot value as an iterable.
     *
     * @param array<array-key, mixed> $scope The data scope to read from.
     * @param string $key The slot key.
     * @param ?string $path The slot path for error messages, when it differs from the key.
     * @param mixed $default The compiled default of an optional slot.
     * @return iterable<array-key, mixed>
     */
    public static function items(array $scope, string $key, ?string $path = null, mixed $default = null): iterable
    {
        $value = $scope[$key]
            ?? self::fallback($scope, $key, $path, $default, func_num_args() >= 4, false);

        return is_iterable($value) ? $value : SlotRuntime::items($value, $path ?? $key);
    }

    /**
     * Validate a mapped child or list item value as a nested data scope.
     *
     * @param mixed $value The mapped value.
     * @param string $path The slot path for error messages.
     * @param string $hint The message suffix for a value that is not a scope.
     * @return array<array-key, mixed>
     */
    public static function scope(mixed $value, string $path, string $hint = ''): array
    {
        return SlotRuntime::scope($value, $path, $hint);
    }

    /**
     * Resolve a slot the fast path could not: an optional slot falls back to
     * the compiled default (also when the value is null, exactly like `??`), a
     * required text or raw slot rejects an explicit null, and any other
     * required slot keeps null when the key exists (an attribute omits itself,
     * a scope slot fails its own type check). A missing required slot throws.
     *
     * Only the cold path calls this, so `func_num_args()` at the call site
     * never runs for present, non-null values.
     *
     * @param array<array-key, mixed> $scope The data scope to read from.
     * @param string $key The slot key.
     * @param ?string $path The slot path for error messages, when it differs from the key.
     * @param mixed $default The compiled default of an optional slot.
     * @param bool $optional Whether the slot has a compiled default.
     * @param bool $nullThrows Whether an explicit null fails the slot.
     * @return mixed The slot value.
     */
    private static function fallback(array $scope, string $key, ?string $path, mixed $default, bool $optional, bool $nullThrows): mixed
    {
        if ($optional) {
            return $scope[$key] ?? $default;
        }

        if ($nullThrows) {
            throw MissingSlotException::forPath($path ?? $key, $scope);
        }

        if (array_key_exists($key, $scope)) {
            return null;
        }

        throw MissingSlotException::forPath($path ?? $key, $scope);
    }
}
