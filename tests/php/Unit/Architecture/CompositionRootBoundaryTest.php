<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;

use function dirname;
use function in_array;
use function is_array;
use function sprintf;

/**
 * `Phel\Phel` is the composition root: it owns `Gacela::bootstrap()`, the
 * project-layout detection and the process-wide runtime argv.
 *
 * {@see ModuleDependencyCycleTest} pins the `Phel <-> Run` pair, but a cycle
 * test can only see edges that point *both* ways. A module that merely reaches
 * *into* the root is invisible there, and `Profile` already does exactly that.
 * So the consumer set is pinned here instead: bootstrapping is a privilege of
 * the entry point, and every module that helps itself to it should be a
 * deliberate, listed decision rather than an accident nobody notices.
 */
final class CompositionRootBoundaryTest extends TestCase
{
    use ScansPhpSourcesTrait;

    /**
     * The modules allowed to import `Phel\Phel`, and why.
     *
     * Both call one method, `setupRuntimeArgs()`, because both launch a
     * user-supplied entry point and must publish its `$argv` before handing
     * control over. Neither bootstraps the container: that stays in `bin/phel`.
     *
     * @var array<string, string> relative file => the single root API it uses
     */
    private const array ALLOWED_ROOT_CONSUMERS = [
        'Profile/Infrastructure/Command/ProfileCommand.php' => 'setupRuntimeArgs',
        'Run/Infrastructure/Command/RunCommand.php' => 'setupRuntimeArgs',
    ];

    /**
     * Bootstrapping the Gacela container is what makes something a composition
     * root. Exactly one file in the repository may do it.
     */
    private const string BOOTSTRAP_ENTRY_POINT = 'bin/phel';

    public function test_only_the_listed_modules_import_the_composition_root(): void
    {
        $importers = [];

        foreach ($this->phpFilesIn('src/php') as $relative => $contents) {
            if (preg_match('/^use\s+Phel\\\\Phel\s*;/m', $contents) !== 1) {
                continue;
            }

            $importers[] = $relative;
        }

        sort($importers);

        self::assertSame(
            array_keys(self::ALLOWED_ROOT_CONSUMERS),
            $importers,
            "A new module imports the composition root Phel\\Phel.\n"
            . "Reaching into the root couples a module to process-wide bootstrap state that a\n"
            . "facade cannot mediate. Prefer taking what you need as a constructor argument.\n"
            . 'If the dependency is genuinely warranted, list it in ALLOWED_ROOT_CONSUMERS with a reason.',
        );
    }

    public function test_root_consumers_use_only_the_runtime_argv_entry_point(): void
    {
        foreach (self::ALLOWED_ROOT_CONSUMERS as $relative => $allowedMethod) {
            $contents = $this->phpFilesIn('src/php')[$relative] ?? '';

            preg_match_all('/(?<![\w\\\\])Phel::(\w+)\s*\(/', $contents, $matches);
            $called = array_values(array_unique($matches[1]));

            self::assertSame(
                [$allowedMethod],
                $called,
                sprintf(
                    "%s now calls more of the composition root than %s().\n"
                    . 'Widening this is how a command quietly turns into a second composition root.',
                    $relative,
                    $allowedMethod,
                ),
            );
        }
    }

    public function test_only_the_cli_entry_point_bootstraps_the_container(): void
    {
        $repositoryRoot = dirname(__DIR__, 4);
        $bootstrappers = [];

        foreach ($this->phpFilesIn('src/php') as $relative => $contents) {
            // Root-namespace files (`Phel.php` and its helpers) *are* the
            // composition root, so bootstrapping is their job by definition.
            if (!str_contains($relative, '/')) {
                continue;
            }

            if ($this->callsBootstrap($contents)) {
                $bootstrappers[] = 'src/php/' . $relative;
            }
        }

        foreach (['bin/phel', 'build/preload.php', 'build/build-phar.php'] as $script) {
            $path = $repositoryRoot . '/' . $script;
            if (!is_file($path)) {
                continue;
            }

            if ($this->callsBootstrap((string) file_get_contents($path))) {
                $bootstrappers[] = $script;
            }
        }

        self::assertSame(
            [self::BOOTSTRAP_ENTRY_POINT],
            $bootstrappers,
            "Something other than bin/phel now bootstraps the container.\n"
            . 'Two composition roots means two different wirings of the same modules can exist at once.',
        );
    }

    /**
     * True when the file really calls `Gacela::bootstrap()` / `Phel::bootstrap()`.
     *
     * Comments are stripped first: `MergedConfigCacheInvalidator` documents the
     * bootstrap contract in a docblock without invoking it, and a docblock that
     * reads like a call is precisely the false positive the other architecture
     * tests already refuse to count.
     */
    private function callsBootstrap(string $contents): bool
    {
        $code = '';

        foreach (token_get_all($contents) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return preg_match('/(?:Gacela|Phel)::bootstrap\s*\(/', $code) === 1;
    }
}
