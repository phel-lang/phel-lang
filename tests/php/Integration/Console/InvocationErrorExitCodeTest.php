<?php

declare(strict_types=1);

namespace PhelTest\Integration\Console;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function getenv;
use function is_dir;
use function mkdir;
use function random_bytes;
use function realpath;
use function sys_get_temp_dir;
use function trim;
use function unlink;

use const PHP_BINARY;

/**
 * A command that could not run as asked exits 2: a path that does not exist,
 * an unknown option value, a config or baseline file that is not there. It
 * used to exit 0 or 1 depending on the command, or drop the missing path and
 * carry on (#3525).
 */
final class InvocationErrorExitCodeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = realpath(sys_get_temp_dir()) . '/phel-exit-codes-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0755, true);
        file_put_contents($this->dir . '/ok.phel', "(ns app.ok)\n(println 1)\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/ok.phel');
        if (is_dir($this->dir)) {
            @rmdir($this->dir);
        }
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function provideInvocationErrors(): iterable
    {
        yield 'lint, missing path' => [['lint', 'missing.phel']];
        yield 'lint, one of two paths missing' => [['lint', 'ok.phel', 'missing.phel']];
        yield 'lint, missing --config' => [['lint', '--config=nope.phel', 'ok.phel']];
        yield 'balance, missing path' => [['balance', 'missing.phel']];
        yield 'analyze, missing path' => [['analyze', 'missing.phel']];
        yield 'test, missing path' => [['test', 'missing.phel']];
        yield 'bench, missing path' => [['bench', 'missing.phel']];
        yield 'bench, missing --ref baseline' => [['bench', '--ref=nope.json', 'ok.phel']];
        yield 'run, unknown path or namespace' => [['run', 'missing.phel']];
        yield 'ns, unknown namespace' => [['ns', 'missing.phel']];
        yield 'format, missing path' => [['format', 'missing.phel']];
        yield 'format --dry-run, missing path' => [['format', '--dry-run', 'missing.phel']];
        yield 'index, missing dir' => [['index', 'missing']];
        yield 'profile, unknown path or namespace' => [['profile', 'missing.phel']];
        yield 'config, unknown --format' => [['config', '--format=xml']];
        yield 'mutate, unknown --reporter' => [['mutate', '--reporter=xml']];
        yield 'build, non-integer -O' => [['build', '-O', 'fast']];
        yield 'doc, unknown --format' => [['doc', '--format=xml']];
        yield 'test, unknown --reporter' => [['test', '--reporter=xml']];
    }

    /**
     * @param list<string> $args
     */
    #[DataProvider('provideInvocationErrors')]
    public function test_an_invocation_error_exits_2_with_the_problem_on_stderr_only(array $args): void
    {
        $result = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', ...$args],
            $this->dir,
        );

        self::assertSame(2, $result->exitCode, $result->stderr . $result->stdout);
        self::assertSame('', $result->stdout);
        self::assertNotSame('', $result->stderr);
    }

    public function test_an_invalid_optimization_level_variable_exits_2_naming_it(): void
    {
        $result = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', 'run', 'ok.phel'],
            $this->dir,
            env: [...getenv(), 'PHEL_OPTIMIZATION_LEVEL' => 'fast'],
        );

        self::assertSame(2, $result->exitCode, $result->stderr . $result->stdout);
        self::assertSame('', $result->stdout);
        self::assertSame(
            'PHEL_OPTIMIZATION_LEVEL must be a non-negative integer such as 0 or 2, got "fast".',
            trim($result->stderr),
        );
    }

    public function test_a_missing_path_is_named_on_stderr(): void
    {
        $result = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', 'lint', 'ok.phel', 'missing.phel'],
            $this->dir,
        );

        self::assertStringContainsString('Path not found: missing.phel', $result->stderr);
        self::assertStringNotContainsString('missing.phel', $result->stdout);
    }
}
