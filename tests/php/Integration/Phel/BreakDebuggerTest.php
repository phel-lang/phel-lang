<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phel;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;
use function sprintf;

/**
 * Runs a script containing `(break)` end-to-end. With no interactive terminal
 * attached (the case for CI, pipes, and this subprocess), the compiled program
 * must skip the debugger and resume instead of blocking on or consuming stdin.
 * The interactive read/eval loop itself is covered by the unit tests, which
 * drive injected streams; here we only guard against a hang or a stolen stdin.
 */
final class BreakDebuggerTest extends TestCase
{
    public function test_break_resumes_without_hanging_when_input_is_piped(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runScript("(continue)\n");

        self::assertSame(0, $exitCode, $this->failureMessage($stdout, $stderr));
        self::assertStringContainsString('breakpoint skipped', $stderr);
        self::assertStringContainsString('result: 42', $stdout);
    }

    public function test_break_resumes_when_stdin_is_closed(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runScript('');

        self::assertSame(0, $exitCode, $this->failureMessage($stdout, $stderr));
        self::assertStringContainsString('breakpoint skipped', $stderr);
        self::assertStringContainsString('result: 42', $stdout);
    }

    /**
     * @return array{0: int, 1: string, 2: string} exit code, stdout, stderr
     */
    private function runScript(string $stdin): array
    {
        $repoRoot = dirname(__DIR__, 4);
        $process = Subprocess::run(
            [PHP_BINARY, $repoRoot . '/bin/phel', 'run', $repoRoot . '/tests/php/Integration/Phel/Fixtures/break-e2e.phel'],
            $repoRoot,
            $stdin,
        );

        return [$process->exitCode, $process->stdout, $process->stderr];
    }

    private function failureMessage(string $stdout, string $stderr): string
    {
        return sprintf("break e2e script failed.\nSTDOUT:\n%s\nSTDERR:\n%s", $stdout, $stderr);
    }
}
