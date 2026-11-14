<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\TestWorkerHandle;
use PHPUnit\Framework\TestCase;

use function function_exists;
use function json_encode;
use function microtime;
use function proc_open;
use function stream_get_contents;
use function unlink;
use function usleep;

use const JSON_THROW_ON_ERROR;

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

    public function test_crash_report_carries_the_exit_code_and_both_pipes(): void
    {
        $worker = $this->startWorker('echo "PHP Fatal error: boom"; fwrite(STDERR, "on stderr"); exit(3);');

        $this->waitUntilDead($worker);

        self::assertSame('exit code 3', $worker->exitStatus());
        self::assertSame("stdout: PHP Fatal error: boom\nstderr: on stderr", $worker->crashReport());
    }

    public function test_crash_report_names_the_signal_that_killed_the_worker(): void
    {
        if (!function_exists('posix_getpid')) {
            self::markTestSkipped('ext-posix is needed to signal the worker from inside');
        }

        $worker = $this->startWorker('posix_kill(posix_getpid(), 9);');

        $this->waitUntilDead($worker);

        self::assertSame('killed by signal 9', $worker->exitStatus());
    }

    public function test_termination_stops_a_worker_that_ignores_sigterm(): void
    {
        if (!function_exists('pcntl_signal')) {
            self::markTestSkipped('ext-pcntl is needed to ignore SIGTERM');
        }

        $worker = $this->startWorker('pcntl_signal(SIGTERM, SIG_IGN); echo "ready"; usleep(5_000_000);');
        $ready = '';
        $deadline = microtime(true) + 5;
        while ($ready !== 'ready' && microtime(true) < $deadline) {
            $ready .= stream_get_contents($worker->stdoutHandle());
            usleep(10_000);
        }

        try {
            self::assertSame('ready', $ready);
            $startedAt = microtime(true);
            $worker->terminate();
            self::assertLessThan(2, microtime(true) - $startedAt);
            self::assertFalse($worker->isAlive());
        } finally {
            $worker->terminate();
        }
    }

    public function test_termination_removes_files_written_during_the_grace_period(): void
    {
        $worker = $this->startWorker(
            '$paths = json_decode(trim(fgets(STDIN)), true); file_put_contents($paths[0], "ready"); '
            . 'stream_get_contents(STDIN); usleep(50_000); '
            . 'foreach ($paths as $path) { file_put_contents($path, "finished"); }',
        );
        $paths = [$worker->resetProgressFile(), $worker->junitFile()];
        $worker->assign(0, 'app.finishing-test', json_encode($paths, JSON_THROW_ON_ERROR) . "\n");
        $ready = '';
        $deadline = microtime(true) + 5;
        while ($ready !== 'ready' && microtime(true) < $deadline) {
            $ready = $worker->lastStartedTest();
            usleep(10_000);
        }

        try {
            self::assertSame('ready', $ready);
            $worker->terminate();
            foreach ($paths as $path) {
                self::assertFileDoesNotExist($path);
            }
        } finally {
            $worker->terminate();
            foreach ($paths as $path) {
                @unlink($path);
            }
        }
    }

    private function startWorker(string $code): TestWorkerHandle
    {
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
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
