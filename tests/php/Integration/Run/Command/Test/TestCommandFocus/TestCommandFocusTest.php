<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandFocus;

use Override;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
use function mkdir;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

/**
 * `^:focus` seen from the command line: the run is narrowed and announced,
 * it passes locally, and it fails on a CI runner or with `--fail-on-focus`,
 * serial and parallel alike.
 */
final class TestCommandFocusTest extends TestCase
{
    private string $projectDir;

    private string $repoRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 7);
        $this->projectDir = sys_get_temp_dir() . '/phel-focus-' . bin2hex(random_bytes(8));
        mkdir($this->projectDir . '/tests', 0o755, true);
        mkdir($this->projectDir . '/vendor', 0o755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\nreturn new \\Phel\\Config\\PhelConfig()\n"
            . "    ->withSrcDirs(['src'])->withTestDirs(['tests'])->withVendorDir('');\n",
        );
        file_put_contents(
            $this->projectDir . '/tests/focus_test.phel',
            "(ns app.focus-test\n  (:require phel.test :refer [deftest is]))\n\n"
            . "(deftest plain-a\n  (is true))\n\n"
            . "(deftest ^:focus focused-b\n  (is true))\n\n"
            . "(deftest ^{:skip \"not today\"} skipped-c\n  (is false))\n",
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_locally_a_focused_run_passes_and_says_it_is_focused(): void
    {
        [$exitCode, $output] = $this->runPhelTest([], 'CI=');

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('Focused run: 1 test(s), 2 ignored', $output);
        self::assertMatchesRegularExpression('/Total:\s*1/', $output);
    }

    public function test_on_ci_a_focused_run_fails(): void
    {
        [$exitCode, $output] = $this->runPhelTest([], 'CI=true');

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('narrowed by ^:focus; failing because CI is set', $output);
    }

    public function test_fail_on_focus_fails_a_parallel_run_too(): void
    {
        [$exitCode, $output] = $this->runPhelTest(['--fail-on-focus', '--parallel=2'], 'CI=');

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('failing because --fail-on-focus was given', $output);
    }

    public function test_list_shows_the_skip_reason_and_honours_focus(): void
    {
        [$exitCode, $output] = $this->runPhelTest(['--list'], 'CI=');

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('+ app.focus-test/focused-b', $output);
        self::assertStringNotContainsString('plain-a', $output);
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string}
     */
    private function runPhelTest(array $arguments, string $env): array
    {
        $args = '';
        foreach ($arguments as $argument) {
            $args .= ' ' . escapeshellarg($argument);
        }

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && ' . $env . ' php ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' test' . $args . ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
