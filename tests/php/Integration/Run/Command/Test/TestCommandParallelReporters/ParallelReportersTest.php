<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandParallelReporters;

use PhelTest\Support\SharedStdlibCache;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function mkdir;
use function random_bytes;
use function realpath;
use function simplexml_load_string;
use function sort;
use function sprintf;
use function sys_get_temp_dir;

use const PHP_BINARY;

/**
 * A parallel run reports through the selected reporter what a serial run
 * reports, and keeps stdout to the machine output (#3627).
 */
final class ParallelReportersTest extends TestCase
{
    private const array TEST_FILES = ['tests/app/a_test.phel', 'tests/app/b_test.phel'];

    private static string $projectDir;

    public static function setUpBeforeClass(): void
    {
        self::$projectDir = realpath(sys_get_temp_dir()) . '/phel-parallel-reporters-' . bin2hex(random_bytes(6));
        mkdir(self::$projectDir . '/tests/app', 0o755, true);
        mkdir(self::$projectDir . '/vendor', 0o755, true);
        file_put_contents(
            self::$projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", self::repoRoot()),
        );
        file_put_contents(
            self::$projectDir . '/phel-config.php',
            "<?php\nreturn new \\Phel\\Config\\PhelConfig()\n"
            . "    ->withSrcDirs([])->withTestDirs(['tests'])->withVendorDir('');\n",
        );
        file_put_contents(
            self::$projectDir . '/tests/app/a_test.phel',
            "(ns app.a-test\n  (:require phel.test :refer [deftest is]))\n\n"
            . "(deftest a-passes\n  (is (= 1 1)))\n\n"
            . "(deftest a-fails\n  (is (= 1 2) \"one is two\"))\n",
        );
        file_put_contents(
            self::$projectDir . '/tests/app/b_test.phel',
            "(ns app.b-test\n  (:require phel.test :refer [deftest is]))\n\n"
            . "(deftest b-passes\n  (is (= 2 2)))\n\n"
            . "(deftest b-errors\n  (is (= 1 (php/intdiv 1 0))))\n",
        );
    }

    public static function tearDownAfterClass(): void
    {
        exec('rm -rf ' . escapeshellarg(self::$projectDir));
    }

    public function test_junit_xml_on_stdout_lists_every_test_the_serial_run_lists(): void
    {
        $serial = $this->phel(['test', '--reporter=junit-xml', ...self::TEST_FILES]);
        $parallel = $this->phel(['test', '--reporter=junit-xml', '--parallel=2', ...self::TEST_FILES]);

        self::assertSame(1, $serial->exitCode, $serial->stderr . $serial->stdout);
        self::assertSame(1, $parallel->exitCode, $parallel->stderr . $parallel->stdout);
        self::assertSame($this->junitSummary($serial->stdout), $this->junitSummary($parallel->stdout));
        self::assertStringContainsString('parallel worker(s)', $parallel->stderr);
    }

    public function test_junit_xml_to_a_file_lists_every_test_the_serial_run_lists(): void
    {
        $serial = $this->phel(['test', '--reporter=junit-xml', '-o', 'serial.xml', ...self::TEST_FILES]);
        $parallel = $this->phel(['test', '--reporter=junit-xml', '-o', 'parallel.xml', '--parallel=2', ...self::TEST_FILES]);

        self::assertSame(1, $serial->exitCode, $serial->stderr . $serial->stdout);
        self::assertSame(1, $parallel->exitCode, $parallel->stderr . $parallel->stdout);
        self::assertSame(
            $this->junitSummary((string) file_get_contents(self::$projectDir . '/serial.xml')),
            $this->junitSummary((string) file_get_contents(self::$projectDir . '/parallel.xml')),
        );
    }

    public function test_tap_prints_what_the_serial_run_prints(): void
    {
        $serial = $this->phel(['test', '--reporter=tap', ...self::TEST_FILES]);
        $parallel = $this->phel(['test', '--reporter=tap', '--parallel=2', '-v', ...self::TEST_FILES]);

        self::assertSame(1, $parallel->exitCode, $parallel->stderr . $parallel->stdout);
        self::assertStringStartsWith('TAP version 13', $parallel->stdout, $parallel->stderr);
        self::assertSame($serial->stdout, $parallel->stdout);
    }

    /**
     * @return array{totals: array<string, string>, cases: list<string>}
     */
    private function junitSummary(string $xml): array
    {
        $root = simplexml_load_string($xml);
        self::assertInstanceOf(SimpleXMLElement::class, $root, $xml);

        $cases = [];
        foreach ($root->testsuite as $suite) {
            foreach ($suite->testcase as $case) {
                $verdict = isset($case->failure) ? 'failure' : (isset($case->error) ? 'error' : 'pass');
                $cases[] = $suite['name'] . '/' . $case['name'] . ':' . $verdict;
            }
        }

        sort($cases);

        return [
            'totals' => [
                'tests' => (string) $root['tests'],
                'failures' => (string) $root['failures'],
                'errors' => (string) $root['errors'],
                'skipped' => (string) $root['skipped'],
            ],
            'cases' => $cases,
        ];
    }

    /**
     * @param list<string> $args
     */
    private function phel(array $args): Subprocess
    {
        $cacheDir = SharedStdlibCache::dir();

        return Subprocess::run(
            [PHP_BINARY, self::repoRoot() . '/bin/phel', ...$args],
            self::$projectDir,
            null,
            $cacheDir === null ? null : [...getenv(), 'PHEL_CACHE_DIR' => $cacheDir],
        );
    }

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 7);
    }
}
