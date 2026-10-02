<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Eval;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function getenv;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_BINARY;

/**
 * The colour decision is made by the console from the real stdout, so it only
 * shows in a child process whose stdout is a pipe.
 */
final class OutputColourSubprocessTest extends TestCase
{
    private const string ESC = "\033[";

    public function test_piped_value_has_no_escape_codes(): void
    {
        $process = $this->phel(['eval', '#{1 2}']);

        self::assertSame("#{1 2}\n", $process->stdout);
    }

    public function test_piped_error_has_no_escape_codes(): void
    {
        $process = $this->phel(['eval', '(foo)']);

        self::assertStringContainsString("[PHEL001] Cannot resolve symbol 'foo'", $process->stdout);
        self::assertStringNotContainsString(self::ESC, $process->stdout . $process->stderr);
    }

    public function test_piped_run_error_has_no_escape_codes(): void
    {
        $base = (string) tempnam(sys_get_temp_dir(), 'phel-colour');
        $file = $base . '.phel';
        file_put_contents($file, "(ns colour-test)\n(foo)\n");

        try {
            $process = $this->phel(['run', $file]);
        } finally {
            unlink($file);
            unlink($base);
        }

        self::assertStringContainsString('[PHEL001]', $process->stdout);
        self::assertStringNotContainsString(self::ESC, $process->stdout . $process->stderr);
    }

    public function test_ansi_flag_restores_colour_on_values_and_errors(): void
    {
        self::assertStringContainsString(self::ESC, $this->phel(['eval', '--ansi', '#{1 2}'])->stdout);
        self::assertStringContainsString(self::ESC . '34m[PHEL001]', $this->phel(['eval', '--ansi', '(foo)'])->stdout);
    }

    public function test_no_ansi_flag_wins_over_a_colour_capable_terminal(): void
    {
        $process = $this->phel(['eval', '--no-ansi', '#{1 2}'], ['FORCE_COLOR' => '1']);

        self::assertSame("#{1 2}\n", $process->stdout);
    }

    public function test_no_color_wins_over_a_colour_capable_terminal(): void
    {
        $process = $this->phel(['eval', '#{1 2}'], ['FORCE_COLOR' => '1', 'NO_COLOR' => '1']);

        self::assertSame("#{1 2}\n", $process->stdout);
    }

    public function test_colour_capable_terminal_keeps_colour(): void
    {
        self::assertStringContainsString(self::ESC, $this->phel(['eval', '#{1 2}'], ['FORCE_COLOR' => '1'])->stdout);
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     */
    private function phel(array $args, array $env = []): Subprocess
    {
        $root = dirname(__DIR__, 6);

        return Subprocess::run(
            [PHP_BINARY, $root . '/bin/phel', ...$args],
            $root,
            env: [
                'PHEL_NO_OPCACHE_REEXEC' => '1',
                'PATH' => (string) getenv('PATH'),
                'HOME' => (string) getenv('HOME'),
                'TMPDIR' => (string) (getenv('TMPDIR') ?: '/tmp'),
                ...$env,
            ],
        );
    }
}
