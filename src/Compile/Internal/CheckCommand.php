<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

use Closure;
use Pure\Compile\Compile;
use Pure\Compile\Shape;
use Pure\Compile\Template;
use Pure\Component\Prop;
use Pure\Component\Registry;
use Pure\Core\Suggestion;
use Pure\Core\Tag;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Throwable;

/**
 * The `pure check` command: validates component contracts statically.
 *
 * For every `*.cmp.php` unit it compares the slots its template reads with the
 * bindings its prepare() hook returns (or with the props its call sites bind,
 * for a unit without prepare()), and checks the typed parameters of the hook
 * against the slot kinds; `*.shape.php` templates are checked for slots bound
 * to incompatible kinds. This is not `pure compile --check`, which reports
 * stale or missing artifacts.
 *
 * @internal
 */
final class CheckCommand
{
    /**
     * The Call methods that are not props; a chain entry naming one is only
     * reported when the target has a prop of that name.
     */
    private const CALL_METHODS = ['props', 'render'];

    private const USAGE = <<<'USAGE'
        Pure contract checker.

        Usage:
          pure check <path>... [--strict]

        Checks every *.cmp.php unit: the slots its template reads against the
        keys its prepare() hook returns — with the declarations on a parameter
        (#[Prop]: slot, item, required, deprecated; #[Trusted]: markup) and on
        the hook (#[Binds]: the returned keys) verified against the signature
        and the template — or, for a unit without prepare(), against the props
        its call sites bind. The hook's parameter types are checked against the
        slot kinds (a list slot needs an iterable, a child scope an array, a
        text slot a stringable).
        Also checks the fluent calls in every file: a `->prop(...)` the target
        does not accept is an error. Reports a slot that one template uses as
        both a scalar and a scope, and checks *.shape.php templates for the
        same conflict. Directories are searched recursively.

          --strict     exit 1 on warnings too
          -h, --help   show this help
          --           treat every later argument as a path

        This is not `pure compile --check`, which reports stale artifacts.

        Exit codes:
          0  no error (and no warning under --strict)
          1  the run found errors, or a file could not be read
          2  the command line was wrong

        USAGE;

    private readonly UnitLoader $loader;
    private readonly ContractChecker $checker;

    /**
     * @param (Closure(string): array<string, array{factory: Closure(): mixed}>)|null $units
     *     Resolves the units registered by a `*.cmp.php` file; null disables
     *     unit support (plain ArtifactCompiler use).
     */
    public function __construct(?Closure $units = null)
    {
        $this->loader = new UnitLoader($units);
        $this->checker = new ContractChecker();
    }

    /**
     * @param list<string> $argv The raw argv, with the command at index 1.
     * @param resource|null $stdout The output stream (defaults to STDOUT).
     * @param resource|null $stderr The error stream (defaults to STDERR).
     * @return int The exit code.
     */
    public function run(array $argv, $stdout = null, $stderr = null): int
    {
        $stdout ??= STDOUT;
        $stderr ??= STDERR;
        $arguments = array_slice($argv, 2);
        $paths = [];
        $strict = false;
        $literal = false;

        foreach ($arguments as $argument) {
            if ($literal) {
                $paths[] = $argument;

                continue;
            }

            if ($argument === '--') {
                $literal = true;

                continue;
            }

            if ($argument === '--strict') {
                $strict = true;

                continue;
            }

            if ($argument === '-h' || $argument === '--help') {
                fwrite($stdout, self::USAGE);

                return 0;
            }

            if (str_starts_with($argument, '-')) {
                fwrite($stderr, "pure: unknown option '{$argument}'.\n\n" . self::USAGE);

                return 2;
            }

            $paths[] = $argument;
        }

        if ($paths === []) {
            fwrite($stderr, "pure: check needs at least one file or directory.\n\n" . self::USAGE);

            return 2;
        }

        $files = [];
        $failed = 0;

        foreach ($paths as $path) {
            try {
                foreach (UnitFinder::discover($path) as $file) {
                    $files[$file] = true;
                }
            } catch (Throwable $error) {
                $failed++;
                fwrite($stderr, "pure: {$error->getMessage()}\n");
            }
        }

        $checked = 0;
        $errors = 0;
        $warnings = 0;
        $notes = 0;

        // Load every unit first: a fluent call in one file may target a
        // component registered by another one. The shape tree is built here
        // once per unit, for the unit check and for the item shapes a call
        // site is compared against.
        $loaded = [];
        $trees = [];

        foreach (array_keys($files) as $file) {
            try {
                $loaded[$file] = $this->loader->unitsOf($file);

                foreach ($loaded[$file] ?? [] as $name => $unit) {
                    $shape = Compile::toShape(($unit['factory'])());

                    if (!$shape instanceof Shape) {
                        throw new RuntimeException("component '{$name}': the factory must return a tag tree or Pure\\Compile\\Shape.");
                    }

                    $trees[$name] = $shape->tree();
                }
            } catch (Throwable $error) {
                $failed++;
                unset($files[$file]);
                fwrite($stderr, "pure: {$error->getMessage()}\n");
            }
        }

        foreach (array_keys($files) as $file) {
            try {
                $units = $loaded[$file] ?? null;

                if ($units === null) {
                    $checked++;
                    $shape = ArtifactCompiler::load($file);
                    self::report($stdout, $stderr, $file, null, $this->checker->check(null, $shape->tree(), null), $errors, $warnings, $notes);
                } else {
                    if ($units === []) {
                        throw new RuntimeException('no component unit is registered here; `pure check` skips the file.');
                    }

                    $attributeFindings = self::attributeFindings($file);

                    foreach ($units as $name => $unit) {
                        $checked++;
                        $prepare = Registry::prepare($name);
                        $findings = array_merge(
                            $this->checker->check(
                                $name,
                                $trees[$name],
                                $prepare === null ? FunctionFinder::of($name, $file) : null,
                                $prepare === null ? null : new ReflectionFunction($prepare)
                            ),
                            $attributeFindings
                        );
                        self::report($stdout, $stderr, $file, $name, $findings, $errors, $warnings, $notes);
                    }
                }

                self::reportCallSites($stdout, $stderr, $file, $errors, $warnings, $trees);
            } catch (Throwable $error) {
                $failed++;
                fwrite($stderr, "pure: {$file}: {$error->getMessage()}\n");
            }
        }

        // The note count is only appended when there is one: without it the
        // info lines printed above would have nothing in the summary to match.
        fwrite(
            $stdout,
            "checked {$checked} unit(s): {$errors} error(s), {$warnings} warning(s)"
            . ($notes > 0 ? ", {$notes} note(s)" : '') . ".\n"
        );

        if ($failed > 0 || $errors > 0 || ($strict && $warnings > 0)) {
            return 1;
        }

        return 0;
    }

    /**
     * Check the fluent component calls in one file: every `->prop(...)` of a
     * call must be accepted by its target, which is the prepare() parameter
     * list when the unit has one and its slot list otherwise. Each finding
     * carries the line of the setter that caused it.
     *
     * @param resource $stdout The output stream.
     * @param resource $stderr The error stream.
     * @param array<string, Tag> $trees The data-free tree per registered unit.
     */
    private static function reportCallSites($stdout, $stderr, string $file, int &$errors, int &$warnings, array $trees): void
    {
        foreach (CallSites::of($file, Registry::names()) as $site) {
            if ($site['dynamic']) {
                continue;
            }

            $expected = self::expectedProps($site['name']);

            if ($expected === null) {
                continue;
            }

            $deprecated = self::deprecatedProps($site['name']);

            foreach ($site['props'] as $prop => $line) {
                if (in_array($prop, $expected, true) || in_array($prop, self::CALL_METHODS, true)) {
                    if (isset($deprecated[$prop])) {
                        self::diagnostic($stdout, $stderr, $file, $line, 'warning', "component '{$site['name']}': the call binds '{$prop}', which is deprecated: {$deprecated[$prop]}");
                        $warnings++;
                    }

                    continue;
                }

                if ($prop === 'children') {
                    self::diagnostic($stdout, $stderr, $file, $line, 'error', "component '{$site['name']}': pass children to the call itself, e.g. {$site['name']}(\$children)");
                    $errors++;

                    continue;
                }

                $nearest = Suggestion::nearest($prop, $expected);
                $hint = $nearest === null ? '' : " (did you mean '{$nearest}'?)";

                self::diagnostic($stdout, $stderr, $file, $line, 'error', "component '{$site['name']}': the call binds '{$prop}', which the target does not accept{$hint}");
                $errors++;
            }

            $tree = $trees[$site['name']] ?? null;

            if ($tree === null) {
                continue;
            }

            $slots = self::propSlots($site['name']);

            foreach ($site['items'] as $prop => $items) {
                self::reportItems(
                    $stdout,
                    $stderr,
                    $file,
                    $site['name'],
                    $prop,
                    $site['props'][$prop],
                    $items,
                    $tree,
                    $slots[$prop] ?? $prop,
                    $errors
                );
            }
        }
    }

    /**
     * Compare the item keys of a list prop bound to an array literal with the
     * item shape of its slot: a required item slot the literal omits is an
     * error, as is an item key the shape does not read. A prop whose slot has
     * no item shape (a scalar list, a raw list) is skipped.
     *
     * @param resource $stdout The output stream.
     * @param resource $stderr The error stream.
     * @param int $line The line of the setter the literal is bound to.
     * @param list<array<string, true>> $items The literal keys per item.
     */
    private static function reportItems($stdout, $stderr, string $file, string $name, string $prop, int $line, array $items, Tag $tree, string $slot, int &$errors): void
    {
        $contract = RootSlots::itemSlots($tree, $slot);

        if ($contract === []) {
            return;
        }

        $read = array_keys($contract);

        foreach ($items as $index => $keys) {
            foreach (array_keys($keys) as $key) {
                if (isset($contract[$key])) {
                    continue;
                }

                $nearest = Suggestion::nearest($key, $read);
                $hint = $nearest === null ? '' : " (did you mean '{$nearest}'?)";

                self::diagnostic($stdout, $stderr, $file, $line, 'error', "component '{$name}': item " . ($index + 1) . " of '{$prop}' binds '{$key}', which the item shape of slot '{$slot}' does not read{$hint}");
                $errors++;
            }

            foreach ($contract as $key => $info) {
                if (!$info['required'] || isset($keys[$key])) {
                    continue;
                }

                self::diagnostic($stdout, $stderr, $file, $line, 'error', "component '{$name}': item " . ($index + 1) . " of '{$prop}' does not provide '{$key}', which the item shape of slot '{$slot}' requires");
                $errors++;
            }
        }
    }

    /**
     * The slot each prop of a fluent unit binds: the `#[Prop]` declaration of
     * its prepare() parameter, or the parameter name. A unit without prepare()
     * binds its props to the slots of the same name.
     *
     * @return array<string, string>
     */
    private static function propSlots(string $name): array
    {
        $prepare = Registry::prepare($name);

        if ($prepare === null) {
            return [];
        }

        $slots = [];

        foreach ((new ReflectionFunction($prepare))->getParameters() as $parameter) {
            $declared = null;

            foreach ($parameter->getAttributes(Prop::class) as $attribute) {
                $declared = $attribute->newInstance()->slot;
            }

            $slots[$parameter->getName()] = $declared ?? $parameter->getName();
        }

        return $slots;
    }

    /**
     * The props a fluent call may bind: the prepare() parameters of the unit,
     * or its root slots.
     *
     * @return list<string>|null
     */
    private static function expectedProps(string $name): ?array
    {
        $prepare = Registry::prepare($name);

        if ($prepare !== null) {
            return array_map(
                static fn (ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionFunction($prepare))->getParameters()
            );
        }

        return Registry::slots($name);
    }

    /**
     * The props a fluent call may bind but should not, per the `#[Prop]`
     * declarations of the target: prop name to migration hint.
     *
     * @return array<string, string>
     */
    private static function deprecatedProps(string $name): array
    {
        $prepare = Registry::prepare($name);

        if ($prepare === null) {
            return [];
        }

        $deprecated = [];

        foreach ((new ReflectionFunction($prepare))->getParameters() as $parameter) {
            foreach ($parameter->getAttributes(Prop::class) as $attribute) {
                $prop = $attribute->newInstance();

                if ($prop->deprecated !== null) {
                    $deprecated[$parameter->getName()] = $prop->deprecated;
                }
            }
        }

        return $deprecated;
    }

    /**
     * The findings of a unit file's `#[Template]` functions: a template
     * builder that declares a return type must return a Shape or a tag. The
     * file must already be loaded, which the loader guarantees for every unit
     * file.
     *
     * @param string $file The unit file.
     * @return list<Finding>
     */
    private static function attributeFindings(string $file): array
    {
        $templates = FunctionFinder::attributed($file, Template::class);
        $findings = [];

        foreach ($templates as $template) {
            $return = $template->getReturnType();

            if ($return === null || self::returnsShapeOrTag($return)) {
                continue;
            }

            $findings[] = Finding::error(
                "#[Template] function {$template->getName()}() must declare a return type of Pure\\Compile\\Shape or a tag, got {$return}"
            );
        }

        return $findings;
    }

    /**
     * Whether a declared return type accepts a Shape or a tag tree: the type
     * itself, a union containing one, or an intersection including one.
     */
    private static function returnsShapeOrTag(ReflectionType $type): bool
    {
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $part) {
                if (self::returnsShapeOrTag($part)) {
                    return true;
                }
            }

            return false;
        }

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        return $type->getName() === Shape::class || is_a($type->getName(), Tag::class, true);
    }

    /**
     * Write the findings of one unit. A diagnostic reads as
     * `path:line: level: message`, so an editor and a CI annotation can pick
     * it up; the pass line stays on stdout.
     *
     * @param resource $stdout The output stream.
     * @param resource $stderr The error stream.
     * @param string $file The unit file.
     * @param string|null $name The component name, null for a shape.
     * @param list<Finding> $findings The findings of one unit.
     */
    private static function report($stdout, $stderr, string $file, ?string $name, array $findings, int &$errors, int &$warnings, int &$notes): void
    {
        if ($findings === []) {
            $label = $name === null ? "{$file} (shape)" : "component '{$name}' -> {$file}";
            fwrite($stdout, "ok: {$label}\n");

            return;
        }

        $subject = $name === null ? '' : "component '{$name}': ";

        foreach ($findings as $finding) {
            self::diagnostic($stdout, $stderr, $file, null, $finding->level, $subject . $finding->message);

            if ($finding->level === 'error') {
                $errors++;
            } elseif ($finding->level === 'warning') {
                $warnings++;
            } else {
                $notes++;
            }
        }
    }

    /**
     * Write one diagnostic as a `path:line: level: message` line. An error and
     * a warning go to stderr, so `2>/dev/null` leaves only the results; an
     * info note is a result and stays on stdout.
     *
     * @param resource $stdout The output stream.
     * @param resource $stderr The error stream.
     * @param string $file The file the finding is in.
     * @param int|null $line The line, when the finding has one.
     * @param string $level The finding level.
     * @param string $message The finding text.
     */
    private static function diagnostic($stdout, $stderr, string $file, ?int $line, string $level, string $message): void
    {
        $stream = $level === 'error' || $level === 'warning' ? $stderr : $stdout;

        fwrite($stream, ($line === null ? $file : "{$file}:{$line}") . ": {$level}: {$message}\n");
    }
}
