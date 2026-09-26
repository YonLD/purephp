<?php

declare(strict_types=1);

namespace Pure\Compile\Internal;

use Closure;
use InvalidArgumentException;
use Pure\Compile\Compile;
use Pure\Compile\Shape;
use Pure\Compile\Template;
use Throwable;

/**
 * The `pure compile` command: turns shape and unit files into artifacts.
 *
 * @internal
 */
final class ArtifactCommand
{
    private const USAGE = <<<'USAGE'
        Pure shape compiler.

        Commands:
          pure compile <path>...   write *.pure.php artifacts (and --plain views)
          pure check <path>...     check component contracts (slots, bindings)
          pure -v, --version       print the version
          pure help, pure --help   show this help

        Run `pure check --help` for the contract checks.

        Usage:
          pure compile <path>... [--check] [--plain] [--list]

        Compiles every *.shape.php file that returns a tag tree or a Pure\Compile\Shape and
        every *.cmp.php unit that registers a component into a sibling
        *.pure.php artifact. Directories are searched recursively.

          --check      report stale or missing files without writing (exit 1)
          --plain      also write a *.plain.php view: markup and native PHP that
                       renders without purephp installed
          --list       print the components and shape files found, without compiling
          -h, --help   show this help
          --           treat every later argument as a path

        A *.cmp.php unit registers exactly one component with
        Pure\Component\register(); compiling it requires the
        file, so run the compiler through bin/pure, which wires the registry.

        Exit codes:
          0  every unit compiled, or nothing was stale under --check
          1  a file could not be read or compiled, or --check found stale files
          2  the command line was wrong

        USAGE;

    private readonly UnitLoader $loader;

    /**
     * @param (Closure(string): array<string, array{factory: Closure(): mixed}>)|null $units
     *     Resolves the units registered by a `*.cmp.php` file; null disables
     *     unit support (plain ArtifactCompiler use).
     */
    public function __construct(?Closure $units = null)
    {
        $this->loader = new UnitLoader($units);
    }

    /**
     * @param list<string> $argv The raw arguments, including the program name.
     * @param resource|null $stdout The output stream, defaults to STDOUT.
     * @param resource|null $stderr The error stream, defaults to STDERR.
     * @return int The exit code.
     */
    public function run(array $argv, $stdout = null, $stderr = null): int
    {
        $stdout ??= STDOUT;
        $stderr ??= STDERR;

        $arguments = array_slice($argv, 1);
        $command = array_shift($arguments);

        if ($command === null) {
            fwrite($stdout, self::USAGE);

            return 0;
        }

        if ($command === '-h' || $command === '--help' || $command === 'help') {
            fwrite($stdout, self::USAGE);

            return 0;
        }

        if ($command !== 'compile') {
            fwrite($stderr, "pure: unknown command '{$command}'.\n\n" . self::USAGE);

            return 2;
        }

        $check = false;
        $plain = false;
        $list = false;
        $paths = [];
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

            if ($argument === '--check') {
                $check = true;

                continue;
            }

            if ($argument === '--plain') {
                $plain = true;

                continue;
            }

            if ($argument === '--list') {
                $list = true;

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
            fwrite($stderr, "pure: compile needs at least one file or directory.\n\n" . self::USAGE);

            return 2;
        }

        $failed = 0;
        $stale = 0;
        $files = [];

        foreach ($paths as $path) {
            try {
                foreach (UnitFinder::discover($path) as $file) {
                    $files[] = $file;
                }
            } catch (Throwable $error) {
                $failed++;
                fwrite($stderr, "pure: {$error->getMessage()}\n");
            }
        }

        $files = self::withoutCollisions($files, $stderr, $failed);

        foreach ($files as $file) {
            try {
                $units = $this->loader->unitsOf($file);

                if ($list) {
                    self::printList($stdout, $file, $units);

                    continue;
                }

                if ($units === null) {
                    self::compile($file, null, $check, $plain, $stdout, $stderr, $stale);

                    continue;
                }

                if ($units === []) {
                    throw new InvalidArgumentException(
                        'no component unit is registered here; call Pure\Component\register() in the file.'
                    );
                }

                if (count($units) > 1) {
                    throw new InvalidArgumentException(
                        count($units) . ' component units are registered here; a unit file registers one component.'
                    );
                }

                $result = (reset($units)['factory'])();
                $shape = Compile::toShape($result);

                if ($shape === null) {
                    throw new InvalidArgumentException(
                        'the unit factory must return a tag tree or Pure\\Compile\\Shape, got ' . get_debug_type($result) . '.'
                    );
                }

                self::compile($file, $shape, $check, $plain, $stdout, $stderr, $stale);
            } catch (Throwable $error) {
                $failed++;
                fwrite($stderr, "pure: {$file}: {$error->getMessage()}\n");
            }
        }

        if ($stale > 0) {
            fwrite($stderr, "pure: {$stale} artifact(s) need recompiling.\n");
        }

        return $failed > 0 || $stale > 0 ? 1 : 0;
    }

    /**
     * Drop every file whose artifact target is claimed by another file.
     *
     * `Box.shape.php` and `Box.cmp.php` both compile to `Box.pure.php`, so the
     * last write would win and `--check` would report the same target stale and
     * up to date in one run, forever.
     *
     * @param list<string> $files The discovered files.
     * @param resource $stderr The error stream.
     * @param int $failed The failure counter to update.
     * @return list<string> The files that own their target.
     */
    private static function withoutCollisions(array $files, $stderr, int &$failed): array
    {
        $claimed = [];
        $dropped = [];

        foreach ($files as $file) {
            $target = ArtifactCompiler::artifactPath($file);
            $owner = $claimed[$target] ?? false;

            if ($owner === false) {
                $claimed[$target] = $file;

                continue;
            }

            if ($owner === $file) {
                continue;
            }

            $dropped[$owner] = true;
            $dropped[$file] = true;
            $failed++;
            fwrite(
                $stderr,
                "pure: '{$target}' is claimed by both '{$owner}' and '{$file}'; one file per artifact name.\n"
            );
        }

        return array_values(array_filter($files, static fn (string $f): bool => !isset($dropped[$f])));
    }

    /**
     * Compile one file: a shape file when `$shape` is null (the file is loaded
     * by the compiler), a unit with the given shape otherwise.
     *
     * @param string $file The shape or unit file.
     * @param Shape|null $shape The unit template, when compiling a `*.cmp.php`.
     * @param bool $check Report instead of writing.
     * @param bool $plain Also handle the plain view.
     * @param resource $stdout The output stream.
     * @param resource $stderr The error stream, for the missing and stale reports.
     * @param int $stale The stale counter to update.
     */
    private static function compile(string $file, ?Shape $shape, bool $check, bool $plain, $stdout, $stderr, int &$stale): void
    {
        if ($check) {
            $sources = $shape === null
                ? ArtifactCompiler::buildAll($file, $plain)
                : ArtifactCompiler::buildUnit($file, $shape, $plain);
            $targets = [ArtifactCompiler::artifactPath($file) => $sources['artifact']];

            if ($sources['plain'] !== null) {
                $targets[ArtifactCompiler::plainPath($file)] = $sources['plain'];
            }

            foreach ($targets as $target => $expected) {
                $current = @file_get_contents($target);

                if ($current === false) {
                    $stale++;
                    fwrite($stderr, "missing: {$target}\n");
                } elseif ($current === $expected) {
                    fwrite($stdout, "up to date: {$target}\n");
                } else {
                    $stale++;
                    fwrite($stderr, "stale: {$target}\n");
                }
            }

            return;
        }

        $written = $shape === null
            ? ArtifactCompiler::writeChanged($file, $plain)
            : ArtifactCompiler::writeUnit($file, $shape, $plain);

        if (!$written['artifactWritten'] && !$written['plainWritten']) {
            fwrite($stdout, "unchanged: {$file}\n");

            return;
        }

        $targets = $written['artifact'] . ($written['plain'] === null ? '' : ', ' . $written['plain']);
        fwrite($stdout, "compiled: {$file} -> {$targets}\n");
    }

    /**
     * @param resource $stdout The output stream.
     * @param array<string, array{factory: Closure(): mixed}>|null $units
     */
    private static function printList($stdout, string $file, ?array $units): void
    {
        if ($units === null) {
            fwrite($stdout, "{$file} (shape)\n");
        } else {
            foreach (array_keys($units) as $name) {
                fwrite($stdout, "{$name} -> {$file} (component)\n");
            }
        }

        foreach (FunctionFinder::attributed($file, Template::class) as $template) {
            fwrite($stdout, "{$template->getName()} -> {$file} (template)\n");
        }
    }

}
