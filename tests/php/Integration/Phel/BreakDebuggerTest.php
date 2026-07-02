<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phel;

use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function fclose;
use function fwrite;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;

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
        $command = sprintf(
            '%s %s run %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($repoRoot . '/bin/phel'),
            escapeshellarg($repoRoot . '/tests/php/Integration/Phel/Fixtures/break-e2e.phel'),
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $repoRoot);
        self::assertIsResource($process);

        if ($stdin !== '') {
            fwrite($pipes[0], $stdin);
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    private function failureMessage(string $stdout, string $stderr): string
    {
        return sprintf("break e2e script failed.\nSTDOUT:\n%s\nSTDERR:\n%s", $stdout, $stderr);
    }
}
