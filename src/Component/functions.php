<?php

declare(strict_types=1);

namespace Pure\Component;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;

/**
 * Register a component unit under a name.
 *
 * The factory must be lazy: it is stored as-is and only called when no fresh
 * artifact serves the unit. A unit file registers one component and defines
 * its call function next to it. The first argument is the call function, so
 * the component name exists exactly once in the file — as the function name —
 * and neither the name nor the file can drift apart; the unit file is the file
 * the call function lives in:
 *
 *     function Icon(): Call
 *     {
 *         return component(__FUNCTION__);
 *     }
 *
 *     register(Icon(...), static fn () => svg(
 *         svgUse()->href(Slot::value('href'))
 *     ));
 *
 * Only a unit whose template reads the children slot takes children: it declares
 * them and forwards them to component(); every other unit leaves them out, so
 * its call is a plain prop chain.
 *
 *     function Card(mixed ...$children): Call
 *     {
 *         return component(__FUNCTION__, ...$children);
 *     }
 *
 * A unit may also register a `prepare` closure: the typed props-to-bindings
 * hook of its fluent call. The closure's parameters are the prop contract, so
 * PHP enforces their types and a missing or unknown prop fails before render:
 *
 *     register(Section(...),
 *         factory: static fn () => Compile::shape(...),
 *         prepare: static function (string $section, string $class, callable $item): array {
 *             return ['title' => ..., 'contents' => ..., 'class' => $class];
 *         });
 *
 * @param Closure $call The call function as `Icon(...)`, not an anonymous closure.
 * @param Closure(): mixed|null $factory Builds the template lazily; may return a tag tree or a Shape.
 * @param bool $override Replace an existing registration of the name or file.
 * @param ?Closure $prepare Optional props-to-bindings hook for fluent calls.
 * @return void
 */
function register(Closure $call, ?Closure $factory = null, bool $override = false, ?Closure $prepare = null): void
{
    $reflection = new ReflectionFunction($call);

    if (str_starts_with($reflection->getName(), '{closure')) {
        throw new InvalidArgumentException('register() takes the call function as Icon(...); pass the named function, not an anonymous closure.');
    }

    $name = $reflection->getName();
    $file = (string) ($reflection->getFileName() ?: '');

    if ($factory === null) {
        throw new InvalidArgumentException("register() needs a factory closure for component '{$name}'.");
    }

    if ($file === '') {
        throw new InvalidArgumentException("register() cannot determine the unit file of component '{$name}'; the call function must be declared in a real file.");
    }

    Registry::register($name, $file, $factory, $override, $prepare);
}

/**
 * Start a fluent component call: props are set like tag attributes, children
 * are passed to the call, and the markup is produced on string conversion.
 *
 *     echo Card(h2('Pro'))
 *         ->type('Free')
 *         ->price('0')
 *         ->features(['10 users included', '2 GB of storage'])
 *         ->text('Sign up for free')
 *         ->class('btn btn-lg btn-block btn-outline-primary');
 *
 * The call implements Pure\Core\Markup, so it nests like a tag:
 * `div(Card(...)->type('Free'))`. A prop named after a slot binds that slot;
 * children bind the reserved `children` slot, which a template reads with
 * `Slot::raw('children')`, so the declaration follows the template: forwarding
 * children to a template without that slot throws on render, and a call
 * function that takes none drops them silently.
 *
 * Inside the unit's own call function the name carries no literal:
 * `return component(__FUNCTION__);`.
 *
 * @param string $name A registered component name or a template path.
 * @param mixed ...$children The children of the call, as tag children.
 * @return Call The call object.
 */
function component(string $name, mixed ...$children): Call
{
    return new Call($name, array_values($children));
}
