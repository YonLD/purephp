<?php

declare(strict_types=1);

namespace Pure\Component;

use Closure;
use InvalidArgumentException;
use Pure\Compile\Compile;
use Pure\Compile\Internal\ArtifactCompiler;
use Pure\Compile\Renderer;
use Pure\Compile\Shape;
use Pure\Core\MissingSlotException;
use RuntimeException;
use Throwable;

/**
 * Registry of component units, referenced by name or by file path.
 *
 * A unit is a `*.cmp.php` file that registers a lazy factory and defines the
 * component function next to it:
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
 * The call function declares children only when its template reads the children
 * slot; every other unit takes none, so its call is a plain prop chain.
 *
 * Registration stores the closure only: no I/O, no tree building. At render
 * time the unit is served by its precompiled artifact when that is at least as
 * new as the unit file; only otherwise is the factory called (once per compile
 * generation) and the shape compiled. A name and the path of its unit file
 * resolve to the same binder.
 *
 * @internal
 */
final class Registry
{
    /** @var array<string, array{file: string, factory: Closure(): mixed, prepare: ?Closure}> */
    private static array $units = [];

    /** @var array<string, string> realpath(file) => name */
    private static array $files = [];

    /** @var array<string, string> the name or path as given => cache key */
    private static array $keys = [];

    /** @var array<string, array{binder: Closure(array<int|string, mixed>): string, renderer: Renderer, generation: int}> */
    private static array $binders = [];

    /** @var array<string, array{shape: Shape, generation: int}> */
    private static array $shapes = [];


    private function __construct()
    {
    }

    /**
     * Register one component unit.
     *
     * Registering the same name for the same file again is a no-op; a name
     * owned by another file or a file that already owns another name throws
     * unless `$override` is true.
     *
     * @param string $name The component name used by component().
     * @param string $file The unit file, normally `__FILE__`.
     * @param Closure(): mixed $factory Builds the template; called lazily, may return a tag tree or a Shape.
     * @param bool $override Replace an existing registration.
     * @param ?Closure $prepare Optional props-to-bindings hook for fluent calls.
     * @return void
     */
    public static function register(string $name, string $file, Closure $factory, bool $override = false, ?Closure $prepare = null): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('component name must not be empty.');
        }

        $path = realpath($file);

        if ($path === false) {
            throw new InvalidArgumentException("component '{$name}' unit file '{$file}' does not exist.");
        }

        $registered = self::$units[$name] ?? null;

        if ($registered !== null && $registered['file'] === $path) {
            return;
        }

        if ($registered !== null && !$override) {
            throw new RuntimeException(
                "component '{$name}' is already registered by '{$registered['file']}'; pass override: true to replace it."
            );
        }

        $owner = self::$files[$path] ?? null;

        if ($owner !== null && $owner !== $name && !$override) {
            throw new RuntimeException("'{$path}' is already registered as '{$owner}'; a unit file registers one component.");
        }

        if ($registered !== null) {
            unset(self::$files[$registered['file']]);
            self::forget($name);
        }

        if ($owner !== null && $owner !== $name) {
            unset(self::$units[$owner]);
            self::forget($owner);
        }

        self::$units[$name] = ['file' => $path, 'factory' => $factory, 'prepare' => $prepare];
        self::$files[$path] = $name;
        self::$keys = [];
    }

    /**
     * The prepare() hook of a registered unit: the props-to-bindings closure a
     * fluent call uses to type and transform its props.
     *
     * @param string $name A component name.
     * @return ?Closure The registered hook, or null when the unit has none.
     */
    public static function prepare(string $name): ?Closure
    {
        return self::$units[$name]['prepare'] ?? null;
    }

    /**
     * The root slot manifest of a unit or template, when the renderer carries
     * one: the slot names the template reads, for tooling and for the children
     * check of a fluent call.
     *
     * @internal
     *
     * @param string $nameOrPath A component name or a unit/shape file path.
     * @return list<string>|null The slot names, or null when unknown.
     */
    public static function slots(string $nameOrPath): ?array
    {
        $key = self::key($nameOrPath);
        $generation = Compile::generation();
        $cached = self::$binders[$key] ?? null;

        if ($cached === null || $cached['generation'] !== $generation) {
            self::binder($nameOrPath);
            $cached = self::$binders[$key];
        }

        return $cached['renderer']->slots;
    }

    /**
     * The binder of a registered unit or of a `*.shape.php` template path.
     *
     * @param string $nameOrPath A component name or a unit/shape file path.
     * @return Closure(array<int|string, mixed>): string
     */
    public static function component(string $nameOrPath): Closure
    {
        return self::binder($nameOrPath);
    }

    /**
     * The registered component names, in registration order.
     *
     * @internal
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::$units);
    }

    /**
     * The units registered by one file, for `pure compile`.
     *
     * @param string $file The unit file path.
     * @return array<string, array{factory: Closure(): mixed}>
     */
    public static function unitsFor(string $file): array
    {
        $path = realpath($file) ?: $file;
        $units = [];

        foreach (self::$units as $name => $unit) {
            if ($unit['file'] === $path) {
                $units[$name] = ['factory' => $unit['factory']];
            }
        }

        return $units;
    }

    /**
     * Drop every registration and cache; for tests.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$units = [];
        self::$files = [];
        self::$keys = [];
        self::$binders = [];
        self::$shapes = [];
    }

    private static function binder(string $nameOrPath): Closure
    {
        $key = self::key($nameOrPath);
        $generation = Compile::generation();
        $cached = self::$binders[$key] ?? null;

        if ($cached !== null && $cached['generation'] === $generation) {
            return $cached['binder'];
        }

        $renderer = isset(self::$units[$key])
            ? self::unitRenderer($key)
            : self::templateRenderer(substr($key, strlen('path:')));

        $binder =
            /** @param array<int|string, mixed> $data */
            static function (array $data) use ($renderer, $key): string {
                try {
                    return $renderer->render($data);
                } catch (MissingSlotException|InvalidArgumentException $error) {
                    // The renderer knows the slot contract, not who owns it:
                    // name the unit or template so one failing slot in a page of
                    // components is traceable from the message alone.
                    throw self::withContext($error, $key);
                }
            };

        self::$binders[$key] = ['binder' => $binder, 'renderer' => $renderer, 'generation' => $generation];

        return $binder;
    }

    /**
     * Prefix a slot error with the component or template it came from. The
     * class is preserved (a MissingSlotException stays catchable), with the
     * original attached as the previous exception.
     *
     * @param MissingSlotException|InvalidArgumentException $error The slot error.
     * @param string $key The binder key: a component name or `path:`-prefixed file.
     * @return Throwable The contextual error to throw.
     */
    private static function withContext(Throwable $error, string $key): Throwable
    {
        if (str_starts_with($key, 'path:')) {
            $context = "template '" . substr($key, strlen('path:')) . "'";
        } else {
            $context = "component '{$key}'";
        }

        $message = $context . ': ' . $error->getMessage();

        return $error instanceof MissingSlotException
            ? new MissingSlotException($message, 0, $error)
            : new InvalidArgumentException($message, 0, $error);
    }

    /**
     * Resolve a name or path to a cache key: a registered name, or a
     * `path:`-prefixed canonical path for a bare template.
     */
    private static function key(string $nameOrPath): string
    {
        if (isset(self::$units[$nameOrPath])) {
            return $nameOrPath;
        }

        if (isset(self::$keys[$nameOrPath])) {
            return self::$keys[$nameOrPath];
        }

        if (self::isPath($nameOrPath)) {
            $path = realpath($nameOrPath) ?: $nameOrPath;

            return self::$keys[$nameOrPath] = self::$files[$path] ?? 'path:' . $path;
        }

        throw new RuntimeException("unknown component '{$nameOrPath}'; " . self::hint() . '.');
    }

    /**
     * The registration context appended to an unresolved name or path.
     */
    private static function hint(): string
    {
        $known = self::names();

        return $known === []
            ? 'no components are registered'
            : 'known components: ' . implode(', ', $known);
    }

    private static function isPath(string $nameOrPath): bool
    {
        return str_contains($nameOrPath, '/')
            || str_contains($nameOrPath, '\\')
            || str_ends_with($nameOrPath, '.php');
    }

    /**
     * The compiled artifact of a source file when it is at least as new as the
     * file, null when the artifact is missing or stale. An artifact that does
     * not return a Renderer is an error, not a stale artifact: the file was
     * compiled by another version of the code.
     */
    private static function freshArtifact(string $file): ?Renderer
    {
        $artifact = ArtifactCompiler::artifactPath($file);
        $artifactTime = is_file($artifact) ? filemtime($artifact) : false;
        $sourceTime = filemtime($file);

        if ($artifactTime === false || $sourceTime === false || $artifactTime < $sourceTime) {
            return null;
        }

        $renderer = require $artifact;

        if (!$renderer instanceof Renderer) {
            throw new RuntimeException(
                "component artifact '{$artifact}' must return a Renderer; run `pure compile`."
            );
        }

        return $renderer;
    }

    /**
     * The compiled renderer of a unit: the precompiled artifact when fresh,
     * the compiled shape otherwise.
     */
    private static function unitRenderer(string $name): Renderer
    {
        $unit = self::$units[$name];

        return self::freshArtifact($unit['file']) ?? self::shape($name)->compile();
    }

    /**
     * The memoized template of a unit; the factory runs at most once per
     * compile generation.
     */
    private static function shape(string $name): Shape
    {
        $generation = Compile::generation();
        $cached = self::$shapes[$name] ?? null;

        if ($cached !== null && $cached['generation'] === $generation) {
            return $cached['shape'];
        }

        $result = (self::$units[$name]['factory'])();
        $shape = self::toShape($result, "component '{$name}' factory");

        self::$shapes[$name] = ['shape' => $shape, 'generation' => $generation];

        return $shape;
    }

    /**
     * The compiled renderer of a bare `*.shape.php` path: the precompiled
     * artifact when fresh, the compiled shape file otherwise.
     */
    private static function templateRenderer(string $shapeFile): Renderer
    {
        if (!is_file($shapeFile)) {
            throw new RuntimeException("component template '{$shapeFile}' does not exist; " . self::hint() . '.');
        }

        $renderer = self::freshArtifact($shapeFile);

        if ($renderer !== null) {
            return $renderer;
        }

        if (str_ends_with($shapeFile, '.cmp.php')) {
            // Requiring a unit registers its component and returns no Shape, so the
            // failure would read "must return a tag tree or Shape" and pass on the next call.
            throw new RuntimeException(
                "'{$shapeFile}' is a component unit and has no fresh artifact; require the unit file to register it and render it by name, or run `pure compile`."
            );
        }

        $result = require $shapeFile;
        $shape = self::toShape($result, "component template '{$shapeFile}'");

        return $shape->compile();
    }

    /**
     * Wrap a tag tree in a Shape, or pass through a Shape as-is.
     */
    private static function toShape(mixed $result, string $subject): Shape
    {
        return Compile::toShape($result) ?? throw new RuntimeException(
            "{$subject} must return a tag tree or Pure\\Compile\\Shape, got " . get_debug_type($result) . '.'
        );
    }

    private static function forget(string $name): void
    {
        unset(
            self::$binders[$name],
            self::$shapes[$name],
        );
    }
}
