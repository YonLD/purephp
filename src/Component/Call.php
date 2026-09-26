<?php

declare(strict_types=1);

namespace Pure\Component;

use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use Pure\Compile\Compile;
use Pure\Core\DevMode;
use Pure\Core\Escaper;
use Pure\Core\Markup;
use Pure\Core\Slot;
use Pure\Core\Suggestion;
use Pure\Core\Tag;

use function Pure\Utils\clx;
use function Pure\Utils\sty;

use ReflectionFunction;
use ReflectionParameter;
use TypeError;

/**
 * A fluent component call: props are set like tag attributes, children are
 * passed to component(), and the markup is produced on string conversion.
 *
 *     div(Card(h2('Pro'))->type('Free')->price('0')->text('Sign up'))
 *
 * Rendering resolves the unit (or template path) through the unit's binder,
 * so artifacts, caching, slot errors and the development
 * guard behave identically. A prop named after a slot binds that slot;
 * `null` leaves a prop unset, exactly like `Tag::setAttr()`; `class()` and
 * `style()` join their arguments like the tag setters do. Children always bind
 * the reserved `children` slot, which a template reads with
 * `Slot::raw('children')`.
 *
 * A unit may register a `prepare` closure to type and transform its props into
 * bindings; when it does, the closure's parameters are the prop contract and
 * unknown or missing props fail before rendering.
 */
final class Call implements Markup
{
    /** @var array<string, mixed> */
    private array $props = [];

    /** @var array<int, mixed> */
    private array $children;

    /** @var array<int, array<string, ReflectionParameter>> */
    private static array $parameters = [];

    /** @var array<string, array{generation: int, deprecated: array<string, string>, trusted: array<string, true>}> */
    private static array $declarations = [];

    /**
     * @internal Component calls are created by Pure\Component\component().
     *
     * @param string $name A registered component name or a template path.
     * @param array<int, mixed> $children The children passed to the call.
     */
    public function __construct(private readonly string $name, array $children = [])
    {
        $this->children = self::flatten($children);
    }

    /**
     * Set one prop. Like a tag attribute setter, the call takes exactly one
     * value and a `null` value leaves the prop unset.
     *
     * @param string $prop The prop name, normally a slot name.
     * @param array<int, mixed> $args The prop value.
     * @return self
     */
    public function __call(string $prop, array $args): self
    {
        if ($prop === 'children') {
            throw new InvalidArgumentException(
                "component '{$this->name}': pass children to the call itself, e.g. {$this->name}(\$children)."
            );
        }

        if (count($args) !== 1) {
            throw new BadMethodCallException(
                "component '{$this->name}': prop '{$prop}()' takes exactly one value."
            );
        }

        return $this->set($prop, $args[0]);
    }

    /**
     * Set the `class` prop, joined like the tag setter: arrays, booleans and
     * `null` behave exactly as `Tag::class()`.
     *
     * @param array<int, array<int|string, mixed>|bool|int|float|string|null> $args
     * @return self
     */
    public function class(array|bool|int|float|string|null ...$args): self
    {
        if (count($args) === 1 && !is_array($args[0])) {
            return $this->set('class', clx($args[0]));
        }

        return $this->set('class', clx(...$args));
    }

    /**
     * Set the `style` prop, joined like the tag setter.
     *
     * @param string|array<string, string|int|float|null>|null $value
     * @return self
     */
    public function style(string|array|null $value): self
    {
        return $this->set('style', is_string($value) || $value === null ? $value : sty($value));
    }

    /**
     * Set several props at once, for a record whose keys are prop names:
     * `Card()->props($row)`. Values follow the setter rules (a `null` leaves a
     * prop unset, a Slot is rejected); use `class()` or `style()` when a value
     * needs the joining semantics of those setters.
     *
     * @param array<array-key, mixed> $props The prop values by prop name.
     * @return self
     */
    public function props(array $props): self
    {
        foreach ($props as $prop => $value) {
            $this->set((string)$prop, $value);
        }

        return $this;
    }

    /**
     * Render the component and return its markup.
     *
     * @return string The rendered markup.
     */
    public function render(): string
    {
        return Registry::component($this->name)($this->data());
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * @return array<string, mixed> The bindings of the call.
     */
    private function data(): array
    {
        $data = $this->props;
        $prepare = Registry::prepare($this->name);

        if ($prepare !== null) {
            $data = self::prepare($prepare, $data, $this->name);
        }

        $slots = Registry::slots($this->name);

        if ($slots !== null && !in_array('children', $slots, true)) {
            if ($this->children !== []) {
                throw new InvalidArgumentException(
                    "component '{$this->name}' does not read children; "
                    . "add Slot::raw('children') to its template or drop them from the call."
                );
            }

            return $data;
        }

        if ($slots !== null || $this->children !== []) {
            // A template with a children slot always receives the list, so a
            // childless call renders empty content exactly like an empty tag.
            $data['children'] = array_map(
                fn (mixed $child): string => $this->childMarkup($child),
                $this->children
            );
        }

        return $data;
    }

    /**
     * Children follow the tag rules: a Tag or Markup child is verbatim markup,
     * everything else is text and is escaped. A Slot cannot be a child of a
     * call — it belongs to the caller's scope, not to the component.
     */
    private function childMarkup(mixed $child): string
    {
        if ($child instanceof Tag) {
            return $child->render();
        }

        if ($child instanceof Markup) {
            return (string)$child;
        }

        if ($child instanceof Slot) {
            throw new InvalidArgumentException(
                "component '{$this->name}': a Slot cannot be a child of a call; "
                . 'bind it to a raw slot instead.'
            );
        }

        return Escaper::text((string)$child);
    }

    /**
     * Flatten nested child arrays like Tag::appendChildren(), so
     * `Card($children)` and `Card(...$children)` are equivalent.
     *
     * @param array<array-key, mixed> $children
     * @return list<mixed>
     */
    private static function flatten(array $children): array
    {
        $flat = [];

        foreach ($children as $child) {
            if ($child === null) {
                continue;
            }

            if (is_array($child)) {
                foreach (self::flatten($child) as $nested) {
                    $flat[] = $nested;
                }

                continue;
            }

            $flat[] = $child;
        }

        return $flat;
    }

    private function set(string $prop, mixed $value): self
    {
        if ($value === null) {
            return $this;
        }

        if ($value instanceof Slot) {
            throw new InvalidArgumentException(
                "component '{$this->name}': prop '{$prop}' cannot be a Slot; "
                . 'a component call binds values, so read the slot in the template and pass the value.'
            );
        }

        $this->guard($prop, $value);
        $this->props[$prop] = $value;

        return $this;
    }

    /**
     * The development guard of one prop: warn when a deprecated prop is set,
     * and when a prop declared as markup receives a value that is not.
     *
     * The declarations are read once per component and compile generation, and
     * only while the guard is on, so a production call pays one boolean.
     */
    private function guard(string $prop, mixed $value): void
    {
        if (!(DevMode::$enabled ?? DevMode::resolve())) {
            return;
        }

        $declarations = self::declarations($this->name);

        if (isset($declarations['deprecated'][$prop])) {
            DevMode::warn(
                "call:{$this->name}:{$prop}:deprecated",
                "component '{$this->name}': prop '{$prop}' is deprecated: {$declarations['deprecated'][$prop]}"
            );
        }

        if (!isset($declarations['trusted'][$prop])) {
            return;
        }

        $untrusted = self::untrusted($value);

        if ($untrusted === []) {
            return;
        }

        DevMode::warn(
            "call:{$this->name}:{$prop}:trusted",
            "component '{$this->name}': prop '{$prop}' is declared as markup (#[Trusted]) but received "
            . get_debug_type($untrusted[0])
            . '; a raw slot emits its value verbatim, so pass Raw::of() for trusted markup and escape everything else.'
        );
    }

    /**
     * The `#[Prop]` and `#[Trusted]` declarations of a unit, read from its
     * prepare() closure once per compile generation.
     *
     * @return array{generation: int, deprecated: array<string, string>, trusted: array<string, true>}
     */
    private static function declarations(string $name): array
    {
        $generation = Compile::generation();
        $cached = self::$declarations[$name] ?? null;

        if ($cached !== null && $cached['generation'] === $generation) {
            return $cached;
        }

        $deprecated = [];
        $trusted = [];
        $prepare = Registry::prepare($name);

        if ($prepare !== null) {
            foreach ((new ReflectionFunction($prepare))->getParameters() as $parameter) {
                foreach ($parameter->getAttributes(Prop::class) as $attribute) {
                    $declaration = $attribute->newInstance();

                    if ($declaration->deprecated !== null) {
                        $deprecated[$parameter->getName()] = $declaration->deprecated;
                    }
                }

                if ($parameter->getAttributes(Trusted::class) !== []) {
                    $trusted[$parameter->getName()] = true;
                }
            }
        }

        return self::$declarations[$name] = [
            'generation' => $generation,
            'deprecated' => $deprecated,
            'trusted' => $trusted,
        ];
    }

    /**
     * The values of a markup prop that are not `Pure\Core\Markup`. Arrays are
     * inspected one level deep, the way a raw slot joins an iterable; a
     * Traversable is left alone because it may be a one-shot generator.
     *
     * @return list<mixed>
     */
    private static function untrusted(mixed $value): array
    {
        if ($value instanceof Markup) {
            return [];
        }

        if (!is_array($value)) {
            return [$value];
        }

        $untrusted = [];

        foreach ($value as $item) {
            if (!$item instanceof Markup) {
                $untrusted[] = $item;
            }
        }

        return $untrusted;
    }

    /**
     * Bind a passed prop against the prepare() parameters: every prop must be
     * declared, every required parameter must be provided, and the call is
     * unpacked by name so PHP enforces the types.
     *
     * @param Closure $prepare The registered prepare closure.
     * @param array<string, mixed> $props The collected props.
     * @param string $name The component name or template path, for messages.
     * @return array<string, mixed> The bindings the closure returns.
     */
    private static function prepare(Closure $prepare, array $props, string $name): array
    {
        $parameters = self::parametersOf($prepare);

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                return self::invoke($prepare, $props, $name);
            }
        }

        // Fast path: the props are exactly the parameters, in any order, so the
        // closure can be unpacked by name without the missing/unknown checks.
        if (count($props) === count($parameters)) {
            $complete = true;

            foreach ($props as $prop => $_) {
                if (!isset($parameters[(string)$prop])) {
                    $complete = false;

                    break;
                }
            }

            if ($complete) {
                return self::invoke($prepare, $props, $name);
            }
        }

        $arguments = [];
        $missing = [];

        foreach ($parameters as $parameterName => $parameter) {
            if (array_key_exists($parameterName, $props)) {
                $arguments[$parameterName] = $props[$parameterName];

                continue;
            }

            if (!$parameter->isOptional()) {
                $missing[] = $parameterName;
            }
        }

        // The unknown props are reported first: a misspelled required prop also
        // leaves that prop missing, and naming the typo the caller can see is
        // more actionable than naming the gap the typo caused.
        $unknown = array_values(array_diff(array_keys($props), array_keys($parameters)));

        if ($unknown !== []) {
            throw new InvalidArgumentException(self::unknownProps($name, $unknown, $parameters));
        }

        if ($missing !== []) {
            throw new InvalidArgumentException("component '{$name}': missing prop " . self::list($missing) . '.');
        }

        return self::invoke($prepare, $arguments, $name);
    }

    /**
     * The unknown-prop error: every prop the call set that prepare() does not
     * declare, each with a "did you mean" when it is one edit away from a
     * parameter.
     *
     * @param string $name The component name or template path, for messages.
     * @param list<string> $unknown The props prepare() does not declare.
     * @param array<string, ReflectionParameter> $parameters The prepare() parameters.
     * @return string The message.
     */
    private static function unknownProps(string $name, array $unknown, array $parameters): string
    {
        $accepted = array_keys($parameters);
        $names = [];

        foreach ($unknown as $prop) {
            $nearest = Suggestion::nearest($prop, $accepted);
            $names[] = $nearest === null ? "'{$prop}'" : "'{$prop}' (did you mean '{$nearest}'?)";
        }

        return "component '{$name}': unknown prop " . implode(', ', $names)
            . '; prepare() accepts ' . self::list($accepted) . '.';
    }

    /**
     * Invoke a prepare() closure, naming the unit in a type error: the closure
     * itself reports as `{closure}()`.
     *
     * @param Closure $prepare The registered prepare closure.
     * @param array<string, mixed> $arguments The props to unpack by name.
     * @param string $name The component name or template path, for messages.
     * @return array<string, mixed> The bindings the closure returns.
     */
    private static function invoke(Closure $prepare, array $arguments, string $name): array
    {
        try {
            return $prepare(...$arguments);
        } catch (TypeError $error) {
            throw new TypeError("component '{$name}': " . $error->getMessage(), 0, $error);
        }
    }

    /**
     * @return array<string, ReflectionParameter>
     */
    private static function parametersOf(Closure $prepare): array
    {
        $id = spl_object_id($prepare);

        if (isset(self::$parameters[$id])) {
            return self::$parameters[$id];
        }

        $parameters = [];

        foreach ((new ReflectionFunction($prepare))->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        return self::$parameters[$id] = $parameters;
    }

    /**
     * @param list<string> $names
     */
    private static function list(array $names): string
    {
        return implode(', ', array_map(static fn (string $name): string => "'{$name}'", $names));
    }
}
