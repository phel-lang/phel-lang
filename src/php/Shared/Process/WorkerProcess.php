<?php

declare(strict_types=1);

namespace Phel\Shared\Process;

use RuntimeException;

use function fclose;
use function fread;
use function fwrite;
use function hexdec;
use function is_resource;
use function microtime;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_select;
use function stream_set_blocking;
use function strlen;
use function substr;
use function usleep;

/**
 * One long-lived worker subprocess seen from the parent: the `proc_open`
 * resource, its three pipes, and the read buffer that {@see WorkerFrame}
 * frames are cut out of.
 *
 * Spawned by `phel test --parallel` (`_test-worker`, Run) and `phel mutate`
 * (`_mutate-worker`, Mutate). It owns the pipe mechanics only; how many
 * workers there are, what a worker is currently assigned, and when one is
 * overdue stay with each caller, because those are the parts that genuinely
 * differ between the two pools.
 */
final class WorkerProcess
{
    private const float GRACEFUL_EXIT_SECONDS = 0.2;

    private const int POLL_INTERVAL_MICROS = 10_000;

    /** @var closed-resource|resource */
    private readonly mixed $stdin;

    /** @var closed-resource|resource */
    private readonly mixed $stdout;

    /** @var closed-resource|resource */
    private readonly mixed $stderr;

    private string $readBuffer = '';

    /**
     * @param closed-resource|resource $process
     * @param array<int, resource>     $pipes
     * @param string                   $writeFailureMessage what {@see write()} throws when the worker stops reading
     */
    private function __construct(
        private readonly mixed $process,
        array $pipes,
        private readonly string $writeFailureMessage,
    ) {
        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        stream_set_blocking($this->stdout, false);
        stream_set_blocking($this->stderr, false);
    }

    /**
     * Null when the process could not be started, so each pool phrases its own
     * spawn failure.
     *
     * @param list<string> $command the full argv, interpreter first
     */
    public static function open(array $command, string $writeFailureMessage): ?self
    {
        $pipes = [];
        $process = @proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            return null;
        }

        return new self($process, $pipes, $writeFailureMessage);
    }

    /**
     * @return resource
     */
    public function stdoutHandle()
    {
        return $this->stdout;
    }

    public function isAlive(): bool
    {
        if (!is_resource($this->process)) {
            return false;
        }

        $status = @proc_get_status($this->process);

        return $status['running'];
    }

    public function write(string $data): void
    {
        while ($data !== '') {
            /** @psalm-suppress PossiblyInvalidArgument */
            $written = @fwrite($this->stdin, $data);
            if ($written === false || $written === 0) {
                throw new RuntimeException($this->writeFailureMessage);
            }

            $data = substr($data, $written);
        }
    }

    /**
     * The next complete frame on stdout, or null while one is still partial.
     *
     * @return array<string, mixed>|null
     */
    public function tryReadFrame(): ?array
    {
        /** @psalm-suppress PossiblyInvalidArgument */
        $chunk = @fread($this->stdout, 65_536);
        if ($chunk !== false && $chunk !== '') {
            $this->readBuffer .= $chunk;
        }

        $headerSize = WorkerFrame::headerSize();
        if (strlen($this->readBuffer) < $headerSize) {
            return null;
        }

        $length = (int) hexdec(substr($this->readBuffer, 0, $headerSize - 1));
        $total = $headerSize + $length;
        if (strlen($this->readBuffer) < $total) {
            return null;
        }

        $body = substr($this->readBuffer, $headerSize, $length);
        $this->readBuffer = substr($this->readBuffer, $total);

        return WorkerFrame::decodeBody($body);
    }

    public function readStderr(): string
    {
        $out = '';
        while (true) {
            /** @psalm-suppress PossiblyInvalidArgument */
            $chunk = @fread($this->stderr, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $out .= $chunk;
        }

        return $out;
    }

    /**
     * Block for at most `$timeoutMicros` until this worker has stdout to read.
     */
    public function waitForOutput(int $timeoutMicros): void
    {
        $reads = [$this->stdout];
        $writes = null;
        $exceptions = null;
        /** @psalm-suppress InvalidArgument */
        @stream_select($reads, $writes, $exceptions, 0, $timeoutMicros);
    }

    /**
     * Closes stdin, gives the worker a moment to exit on its own, then signals
     * and reaps it.
     */
    public function terminate(int $signal = 15): void
    {
        if (is_resource($this->stdin)) {
            /** @psalm-suppress InaccessibleProperty */
            @fclose($this->stdin);
        }

        if (is_resource($this->process)) {
            $deadline = microtime(true) + self::GRACEFUL_EXIT_SECONDS;
            while (microtime(true) < $deadline && $this->isAlive()) {
                usleep(self::POLL_INTERVAL_MICROS);
            }

            if ($this->isAlive()) {
                @proc_terminate($this->process, $signal);
            }

            @proc_close($this->process);
        }

        foreach ([$this->stdout, $this->stderr] as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
    }
}
