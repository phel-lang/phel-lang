<?php

declare(strict_types=1);

namespace PhelTest\Integration\Console;

use PhelTest\Support\SharedStdlibCache;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function explode;
use function file_put_contents;
use function getenv;
use function json_decode;
use function json_encode;
use function mkdir;
use function random_bytes;
use function realpath;
use function simplexml_load_string;
use function sprintf;
use function substr_count;
use function sys_get_temp_dir;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

/**
 * In a machine mode stdout carries only the machine output, so a tool can
 * parse all of it. Progress, timings, notices and what the project prints
 * while it loads go to stderr (#3525).
 */
final class MachineModeStdoutTest extends TestCase
{
    private static string $projectDir;

    public static function setUpBeforeClass(): void
    {
        self::$projectDir = realpath(sys_get_temp_dir()) . '/phel-machine-stdout-' . bin2hex(random_bytes(6));
        mkdir(self::$projectDir . '/src/app', 0o755, true);
        mkdir(self::$projectDir . '/tests/app', 0o755, true);
        mkdir(self::$projectDir . '/vendor', 0o755, true);
        file_put_contents(
            self::$projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", self::repoRoot()),
        );
        file_put_contents(
            self::$projectDir . '/phel-config.php',
            "<?php\nreturn new \\Phel\\Config\\PhelConfig()\n"
            . "    ->withSrcDirs(['src'])->withTestDirs(['tests'])->withVendorDir('');\n",
        );
        file_put_contents(
            self::$projectDir . '/src/app/calc.phel',
            "(ns app.calc)\n\n(println \"loading app.calc\")\n\n(defn add\n  \"Adds two numbers.\"\n  [a b]\n  (+ a b))\n",
        );
        file_put_contents(
            self::$projectDir . '/src/app/main.phel',
            "(ns app.main\n  (:require app.calc :as calc))\n\n(println \"main says\" (calc/add 1 2))\n",
        );
        file_put_contents(
            self::$projectDir . '/tests/app/calc_test.phel',
            "(ns app.calc-test\n  (:require phel.test :refer [deftest is])\n  (:require app.calc :as calc))\n\n"
            . "(deftest adds\n  (is (= 3 (calc/add 1 2))))\n",
        );
    }

    public static function tearDownAfterClass(): void
    {
        exec('rm -rf ' . escapeshellarg(self::$projectDir));
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function provideJsonModes(): iterable
    {
        yield 'analyze' => [['analyze', 'src/app/main.phel'], 'loading app.calc'];
        yield 'lint --format=json' => [['lint', '--format=json', 'src/app/main.phel'], 'loading app.calc'];
        yield 'doc --format=json' => [['doc', '--format=json', '--ns=app.calc', 'add'], 'loading app.calc'];
        yield 'profile --format=json' => [['profile', '--format=json', 'src/app/main.phel'], 'main says 3'];
        yield 'index -o' => [['index', 'src', '-o', 'index.json'], 'Index persisted to: index.json'];
        yield 'mutate --format=json' => [['mutate', '--format=json', 'src/app/calc.phel'], 'Mutating 1 file(s)'];
    }

    /**
     * @param list<string> $args
     */
    #[DataProvider('provideJsonModes')]
    public function test_stdout_is_one_json_document_and_the_rest_goes_to_stderr(array $args, string $onStderr): void
    {
        $result = $this->phel($args);

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        json_decode($result->stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString($onStderr, $result->stderr);
    }

    public function test_mutate_json_sends_what_the_project_prints_while_loading_to_stderr(): void
    {
        $result = $this->phel(['mutate', '--format=json', 'src/app/calc.phel']);

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        self::assertStringNotContainsString('loading app.calc', $result->stdout);
        self::assertStringContainsString('loading app.calc', $result->stderr);
    }

    public function test_the_deprecated_mutate_reporter_alias_still_selects_the_format_and_says_so_once_on_stderr(): void
    {
        $result = $this->phel(['mutate', '--reporter=json', 'src/app/calc.phel']);

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        json_decode($result->stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, substr_count($result->stderr, '--reporter is deprecated; use --format instead.'), $result->stderr);
    }

    public function test_junit_xml_without_output_file_is_one_xml_document(): void
    {
        $result = $this->phel(['test', '--reporter=junit-xml']);

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        self::assertNotFalse(simplexml_load_string($result->stdout), $result->stdout);
        self::assertStringEndsWith('</testsuites>', trim($result->stdout));
        self::assertStringContainsString('Time: ', $result->stderr);
    }

    public function test_tap_ends_with_its_plan_line(): void
    {
        $result = $this->phel(['test', '--reporter=tap']);

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        self::assertStringStartsWith('TAP version 13', $result->stdout);
        self::assertStringEndsWith("\n1..1", trim($result->stdout));
        self::assertStringContainsString('Time: ', $result->stderr);
    }

    public function test_the_default_reporter_keeps_its_timing_on_stdout(): void
    {
        $result = $this->phel(['test']);

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        self::assertStringContainsString('Time: ', $result->stdout);
    }

    public function test_api_daemon_answers_with_json_lines_only(): void
    {
        $request = json_encode([
            'id' => 1,
            'method' => 'analyzeSource',
            'params' => [
                'source' => "(ns app.main\n  (:require app.calc :as calc))\n",
                'uri' => self::$projectDir . '/src/app/main.phel',
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->phel(['api-daemon'], $request . "\n");

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        foreach (explode("\n", trim($result->stdout)) as $line) {
            json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }

        self::assertStringContainsString('loading app.calc', $result->stderr);
    }

    /**
     * @param list<string> $args
     */
    private function phel(array $args, ?string $stdin = null): Subprocess
    {
        $cacheDir = SharedStdlibCache::dir();

        return Subprocess::run(
            [PHP_BINARY, self::repoRoot() . '/bin/phel', ...$args],
            self::$projectDir,
            $stdin,
            $cacheDir === null ? null : [...getenv(), 'PHEL_CACHE_DIR' => $cacheDir],
        );
    }

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 4);
    }
}
