<?php

declare(strict_types=1);

namespace Phel\Run\Application\Bench;

use function fclose;
use function is_resource;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function stream_get_contents;
use function usleep;

/**
 * The subprocesses an A/B run needs: git and composer, whose output is
 * captured, and the two sides' `phel bench`, whose output goes to a log file
 * and which {@see abort()} can stop from a signal handler.
 *
 * @internal
 */
final class ChildProcess
{
    private const int POLL_MICROSECONDS = 50_000;

    /** @var resource|null */
    private $running;

    /**
     * @param list<string> $command
     *
     * @return array{0: int, 1: string, 2: string} exit status, stdout and stderr
     */
    public function capture(array $command, string $cwd): array
    {
        $pipes = [];
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($process)) {
            throw new AbBenchException(sprintf('Cannot run `%s`.', $command[0] ?? ''));
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    /**
     * Runs `$command` to completion with stdout and stderr written to
     * `$logFile`. Polls rather than blocking in `proc_close`, so a signal
     * handler gets to run while the child is still busy.
     *
     * @param list<string>               $command
     * @param array<string, string>|null $env
     */
    public function runLogged(array $command, string $cwd, ?array $env, string $logFile): int
    {
        $log = @fopen($logFile, 'w');
        if ($log === false) {
            throw new AbBenchException(sprintf('Cannot write `%s`.', $logFile));
        }

        $pipes = [];
        $process = @proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => $log, 2 => $log],
            $pipes,
            $cwd,
            $env,
        );
        fclose($log);
        if (!is_resource($process)) {
            throw new AbBenchException(sprintf('Cannot run `%s`.', $command[0] ?? ''));
        }

        fclose($pipes[0]);
        $this->running = $process;

        try {
            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    return $status['exitcode'];
                }

                usleep(self::POLL_MICROSECONDS);
            }
        } finally {
            $this->running = null;
            proc_close($process);
        }
    }

    public function abort(): void
    {
        if (is_resource($this->running)) {
            proc_terminate($this->running);
        }
    }
}
