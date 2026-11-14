<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandParallelTimeout;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function glob;
use function microtime;
use function mkdir;
use function random_bytes;
use function realpath;
use function sprintf;
use function sys_get_temp_dir;

use const PHP_BINARY;

/**
 * `--timeout` stops a namespace that never finishes, reports it as an error
 * naming the test that hung, and lets the rest of the run finish (#3868).
 */
final class ParallelTimeoutTest extends TestCase
{
    private const int TIMEOUT_SECONDS = 3;

    private static string $projectDir;

    public static function setUpBeforeClass(): void
    {
        self::$projectDir = realpath(sys_get_temp_dir()) . '/phel-parallel-timeout-' . bin2hex(random_bytes(6));
        mkdir(self::$projectDir . '/tests/app', 0o755, true);
        mkdir(self::$projectDir . '/vendor', 0o755, true);
        file_put_contents(
            self::$projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", dirname(__DIR__, 7)),
        );
        file_put_contents(
            self::$projectDir . '/phel-config.php',
            "<?php\nreturn new \\Phel\\Config\\PhelConfig()\n"
            . "    ->withSrcDirs([])->withTestDirs(['tests'])->withVendorDir('');\n",
        );
        file_put_contents(
            self::$projectDir . '/tests/app/hang_test.phel',
            "(ns app.hang-test\n  (:require phel.test :refer [deftest is]))\n\n"
            . "(deftest passes-first\n  (is (= 1 1)))\n\n"
            . "(deftest spins-forever\n  (loop [] (recur)))\n",
        );
        file_put_contents(
            self::$projectDir . '/tests/app/ok_test.phel',
            "(ns app.ok-test\n  (:require phel.test :refer [deftest is]))\n\n"
            . "(deftest ok-passes\n  (is (= 2 2)))\n",
        );
    }

    public static function tearDownAfterClass(): void
    {
        exec('rm -rf ' . escapeshellarg(self::$projectDir));
    }

    public function test_a_hung_namespace_fails_within_the_limit_and_the_others_still_run(): void
    {
        $tempDir = self::$projectDir . '/tmp';
        mkdir($tempDir);
        $startedAt = microtime(true);
        $result = $this->phelTest(
            ['--parallel=2', '--timeout=' . self::TIMEOUT_SECONDS, '--reporter=default', '--reporter=junit-xml', '-o', 'junit.xml'],
            [...getenv(), 'TMPDIR' => $tempDir],
        );
        $elapsed = microtime(true) - $startedAt;

        self::assertSame(1, $result->exitCode, $result->stdout . $result->stderr);
        self::assertLessThan(60, $elapsed, 'the run must not wait for the hung test');

        $output = $result->stdout . $result->stderr;
        self::assertStringContainsString(
            sprintf('Timed out after %ds running app.hang-test (last test started: spins-forever).', self::TIMEOUT_SECONDS),
            $output,
        );
        self::assertMatchesRegularExpression('/Passed:\s+1\b/', $output, 'app.ok-test still ran');
        self::assertMatchesRegularExpression('/Error:\s+1\b/', $output);

        $junit = (string) file_get_contents(self::$projectDir . '/junit.xml');
        self::assertStringContainsString('<testcase name="spins-forever" classname="app.hang-test"', $junit);
        self::assertStringContainsString('type="Timeout"', $junit);
        self::assertStringContainsString('<testcase name="ok-passes"', $junit);
        self::assertSame([], glob($tempDir . '/phel-junit-*') ?: [], 'the stopped worker left its JUnit file');
    }

    public function test_without_a_timeout_a_passing_run_behaves_as_before(): void
    {
        $result = $this->phelTest(['--parallel=2', 'tests/app/ok_test.phel']);

        self::assertSame(0, $result->exitCode, $result->stdout . $result->stderr);
        self::assertStringNotContainsString('Timed out', $result->stdout . $result->stderr);
    }

    public function test_a_timeout_with_watch_is_an_invocation_error(): void
    {
        $result = $this->phelTest(['--watch', '--parallel=2', '--timeout=5']);

        self::assertSame(2, $result->exitCode);
        self::assertStringContainsString('--timeout cannot be combined with --watch', $result->stderr);
    }

    public function test_a_timeout_without_parallel_is_an_invocation_error(): void
    {
        $result = $this->phelTest(['--timeout=5']);

        self::assertSame(2, $result->exitCode);
        self::assertStringContainsString('--timeout needs a parallel run', $result->stderr);
    }

    /**
     * @param list<string>               $arguments
     * @param array<string, string>|null $env
     */
    private function phelTest(array $arguments, ?array $env = null): Subprocess
    {
        return Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 7) . '/bin/phel', 'test', ...$arguments],
            self::$projectDir,
            env: $env,
        );
    }
}
