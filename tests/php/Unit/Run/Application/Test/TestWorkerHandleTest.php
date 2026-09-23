<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\TestWorkerHandle;
use PHPUnit\Framework\TestCase;

use function function_exists;
use function microtime;
use function proc_open;
use function strlen;
use function usleep;

final class TestWorkerHandleTest extends TestCase
{
    public function test_command_splices_opcache_flags_between_php_binary_and_script(): void
    {
        $cmd = TestWorkerHandle::buildCommand(
            '/usr/bin/php',
            '/app/bin/phel',
            ['-d', 'opcache.enable_cli=1', '-d', 'opcache.file_cache=/var/phel/opcache-workers'],
        );

        // -d flags must precede the script so PHP applies them; the script and
        // subcommand stay last so argv parsing is unchanged.
        self::assertSame(
            [
                '/usr/bin/php',
                '-d', 'opcache.enable_cli=1',
                '-d', 'opcache.file_cache=/var/phel/opcache-workers',
                '/app/bin/phel', '_test-worker',
            ],
            $cmd,
        );
    }

    public function test_command_without_opcache_flags_is_the_plain_worker_invocation(): void
    {
        self::assertSame(
            ['/usr/bin/php', '/app/bin/phel', '_test-worker'],
            TestWorkerHandle::buildCommand('/usr/bin/php', '/app/bin/phel', []),
        );
    }

    public function test_crash_report_carries_the_exit_code_and_what_the_worker_left_on_its_pipes(): void
    {
        // A PHP fatal error lands on stdout, not stderr: the report must show both.
        $worker = $this->startWorker('echo "PHP Fatal error: boom"; fwrite(STDERR, "on stderr"); exit(3);');

        $this->waitUntilDead($worker);

        self::assertSame('exit code 3', $worker->exitStatus());
        $report = $worker->crashReport();
        self::assertStringContainsString('stdout: PHP Fatal error: boom', $report);
        self::assertStringContainsString('stderr: on stderr', $report);
    }

    public function test_crash_report_names_the_signal_that_killed_the_worker(): void
    {
        if (!function_exists('posix_getpid')) {
            self::markTestSkipped('ext-posix is needed to signal the worker from inside');
        }

        $worker = $this->startWorker('posix_kill(posix_getpid(), 9);');

        $this->waitUntilDead($worker);

        self::assertSame('killed by signal 9', $worker->exitStatus());
        self::assertSame('', $worker->crashReport());
    }

    public function test_a_worker_that_floods_stderr_runs_to_the_end_while_it_is_drained(): void
    {
        // Far past any pipe buffer: undrained, the write blocks forever.
        $worker = $this->startWorker('fwrite(STDERR, str_repeat("x", 1_048_576) . "last line"); exit(0);');

        for ($i = 0; $i < 500 && $worker->isAlive(); ++$i) {
            $worker->drainStderr();
            usleep(10_000);
        }

        $alive = $worker->isAlive();
        $report = $worker->crashReport();
        $worker->terminate();

        self::assertFalse($alive, 'the worker blocked writing to stderr');
        self::assertSame('exit code 0', $worker->exitStatus());
        self::assertStringEndsWith('last line', $report, 'the report keeps the newest output');
        self::assertLessThan(20_000, strlen($report), 'the kept stderr is bounded');
    }

    public function test_one_drain_returns_against_a_worker_that_never_stops_writing(): void
    {
        $worker = $this->startWorker('$s = str_repeat("x", 8192); while (true) { fwrite(STDERR, $s); }');
        usleep(100_000);

        $started = microtime(true);
        $worker->drainStderr();
        $elapsed = microtime(true) - $started;
        $worker->terminate();

        self::assertLessThan(1.0, $elapsed, 'a drain kept reading while the worker kept writing');
    }

    private function startWorker(string $code): TestWorkerHandle
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        return new TestWorkerHandle($process, $pipes);
    }

    private function waitUntilDead(TestWorkerHandle $worker): void
    {
        for ($i = 0; $i < 500 && $worker->isAlive(); ++$i) {
            usleep(10_000);
        }

        self::assertFalse($worker->isAlive(), 'worker did not exit');
    }
}
