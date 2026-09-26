<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

/**
 * Static view of the fluent component calls in a file, for `pure check`.
 *
 * A site is a plain function call to a registered component name followed by
 * the `->prop(...)` chain, e.g. `Card($children)->type('Free')->text('Sign
 * up')`. The scan is deliberately conservative: a spread argument marks the
 * site dynamic (its props are not compared), and only names the checker
 * already resolved are considered.
 *
 * @internal
 */
final class CallSites
{
    private function __construct()
    {
    }

    /**
     * @param string $file The file to scan.
     * @param list<string> $names The registered component names.
     * @return list<array{name: string, props: array<string, int>, items: array<string, list<array<string, true>>>, dynamic: bool}>
     *     `props` holds each prop name with the line of its `->prop(...)`, and
     *     `items` holds, per prop bound to an array literal of array literals,
     *     the literal keys of every item.
     */
    public static function of(string $file, array $names): array
    {
        if ($names === [] || !is_file($file)) {
            return [];
        }

        $source = file_get_contents($file);

        if ($source === false) {
            return [];
        }

        /** @var list<array{0: int, 1: string, 2: int}|string> $tokens */
        $tokens = token_get_all('<?php ' . $source);
        $known = array_flip($names);
        $count = count($tokens);
        $sites = [];

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (!is_array($token) || $token[0] !== T_STRING || !isset($known[$token[1]])) {
                continue;
            }

            $previous = self::significant($tokens, $index, -1);

            if (
                is_array($previous)
                && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_FUNCTION, T_ATTRIBUTE], true)
            ) {
                continue;
            }

            if (self::significant($tokens, $index, 1) !== '(') {
                continue;
            }

            $end = self::closing($tokens, $index);

            if ($end === null) {
                continue;
            }

            [$props, $items, $dynamic] = self::chain($tokens, $end);

            $sites[] = ['name' => $token[1], 'props' => $props, 'items' => $items, 'dynamic' => $dynamic];
        }

        return $sites;
    }

    /**
     * The `->prop(...)` chain after a call, plus whether it is dynamic.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @return array{0: array<string, int>, 1: array<string, list<array<string, true>>>, 2: bool}
     */
    private static function chain(array $tokens, int $end): array
    {
        $props = [];
        $items = [];
        $dynamic = false;
        $cursor = $end;

        while (true) {
            $operatorIndex = self::next($tokens, $cursor);
            $operator = $tokens[$operatorIndex] ?? null;

            if (!is_array($operator) || $operator[0] !== T_OBJECT_OPERATOR) {
                break;
            }

            $nameIndex = self::next($tokens, $operatorIndex);
            $name = $tokens[$nameIndex] ?? null;

            if (!is_array($name) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name[1]) !== 1) {
                break;
            }

            $openIndex = self::next($tokens, $nameIndex);

            if (!isset($tokens[$openIndex]) || $tokens[$openIndex] !== '(') {
                break;
            }

            $close = self::closing($tokens, $openIndex);

            if ($close === null) {
                break;
            }

            if (self::hasSpread($tokens, $openIndex, $close)) {
                $dynamic = true;
            } else {
                $props[$name[1]] = $name[2];
                $literal = self::items($tokens, $openIndex, $close);

                if ($literal !== null) {
                    $items[$name[1]] = $literal;
                }
            }

            $cursor = $close;
        }

        return [$props, $items, $dynamic];
    }

    /**
     * The item keys of an argument that is one array literal of array
     * literals: `->links([['text' => 'Team', 'href' => '#']])`. Null when the
     * argument is anything else — a list of scalars, a variable, a spread — so
     * the item keys are compared only when they are written out.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @return list<array<string, true>>|null
     */
    private static function items(array $tokens, int $open, int $close): ?array
    {
        $first = self::significantIndex($tokens, $open, 1);
        $last = self::significantIndex($tokens, $close, -1);

        if ($first < 0 || $last < 0 || $first >= $last) {
            return null;
        }

        if ($tokens[$first] !== '[' || self::closing($tokens, $first) !== $last) {
            return null;
        }

        $items = [];

        foreach (self::elements($tokens, $first, $last) as [$elementStart, $elementEnd]) {
            $keys = self::itemKeys($tokens, $elementStart, $elementEnd);

            if ($keys === null) {
                return null;
            }

            $items[] = $keys;
        }

        return $items;
    }

    /**
     * The literal string keys of one item array: `['text' => $x, 'href' => $y]`.
     * Null when an element is not a plain `'key' => value` pair, so an item
     * with a spread or a computed key is left to the runtime.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @return array<string, true>|null
     */
    private static function itemKeys(array $tokens, int $start, int $end): ?array
    {
        if ($tokens[$start] !== '[' || self::closing($tokens, $start) !== $end) {
            return null;
        }

        $keys = [];

        foreach (self::elements($tokens, $start, $end) as [$elementStart, $elementEnd]) {
            $key = $tokens[$elementStart];

            if (!is_array($key) || $key[0] !== T_CONSTANT_ENCAPSED_STRING || str_contains($key[1], '\\')) {
                return null;
            }

            $arrow = self::significantIndex($tokens, $elementStart, 1);
            $arrowToken = $tokens[$arrow] ?? null;

            if ($arrow > $elementEnd || !is_array($arrowToken) || $arrowToken[0] !== T_DOUBLE_ARROW) {
                return null;
            }

            $keys[substr($key[1], 1, -1)] = true;
        }

        return $keys;
    }

    /**
     * The top-level elements of an array literal, as [start, end] index pairs
     * of significant tokens. A trailing comma adds no element.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @return list<array{0: int, 1: int}>
     */
    private static function elements(array $tokens, int $open, int $close): array
    {
        $elements = [];
        $current = [];
        $depth = 0;

        for ($cursor = $open + 1; $cursor < $close; $cursor++) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($token === '(' || $token === '[' || $token === '{') {
                $depth++;
            } elseif ($token === ')' || $token === ']' || $token === '}') {
                $depth--;
            } elseif ($token === ',' && $depth === 0) {
                if ($current !== []) {
                    $elements[] = [$current[0], $current[count($current) - 1]];
                    $current = [];
                }

                continue;
            }

            $current[] = $cursor;
        }

        if ($current !== []) {
            $elements[] = [$current[0], $current[count($current) - 1]];
        }

        return $elements;
    }

    /**
     * The index of the next significant token from $index, or -1.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function significantIndex(array $tokens, int $index, int $step): int
    {
        $count = count($tokens);

        for ($cursor = $index + $step; $cursor >= 0 && $cursor < $count; $cursor += $step) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $cursor;
        }

        return -1;
    }

    /**
     * The index of the token that closes the parentheses opened at $index.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function closing(array $tokens, int $index): ?int
    {
        $count = count($tokens);
        $depth = 0;

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if ($token === '(' || $token === '[' || $token === '{') {
                $depth++;
            } elseif ($token === ')' || $token === ']' || $token === '}') {
                $depth--;

                if ($depth === 0) {
                    return $cursor;
                }
            }
        }

        return null;
    }

    /**
     * Whether an argument of the call is unpacked (`->prop(...$values)`), which
     * makes the prop set unknowable.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function hasSpread(array $tokens, int $open, int $close): bool
    {
        $depth = 1;

        for ($cursor = $open + 1; $cursor < $close; $cursor++) {
            $token = $tokens[$cursor];

            if ($token === '(' || $token === '[' || $token === '{') {
                $depth++;
            } elseif ($token === ')' || $token === ']' || $token === '}') {
                $depth = max(1, $depth - 1);
            } elseif ($depth === 1 && is_array($token) && $token[0] === T_ELLIPSIS) {
                return true;
            }
        }

        return false;
    }

    /**
     * The index of the next significant token, or -1 at the end.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function next(array $tokens, int $index): int
    {
        $cursor = $index + 1;
        $count = count($tokens);

        while ($cursor < $count) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $cursor++;

                continue;
            }

            return $cursor;
        }

        return -1;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @return array{0: int, 1: string, 2: int}|string|null
     */
    private static function significant(array $tokens, int $index, int $direction): array|string|null
    {
        $cursor = $index + $direction;

        while (isset($tokens[$cursor])) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $cursor += $direction;

                continue;
            }

            return $token;
        }

        return null;
    }
}
