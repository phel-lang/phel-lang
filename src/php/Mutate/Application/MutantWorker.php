<?php

declare(strict_types=1);

namespace Phel\Mutate\Application;

use Phel\Shared\Process\WorkerFrame;
use RuntimeException;

use function fclose;
use function fread;
use function fwrite;
use function hexdec;
use function is_resource;
use function microtime;
use function preg_match;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function stream_select;
use function stream_set_blocking;
use function strlen;
use function substr;
use function usleep;

/**
 * One `phel _mutate-worker` subprocess seen from the parent. Frames go out
 * with {@see send()} and answers come back through {@see tryReceive()},
 * so a pool can keep several workers busy from one loop; {@see request()}
 * is the blocking pair for the load and baseline steps. The subprocess
 * exists so that a mutant which never terminates or crashes the
 * interpreter costs one worker, not the run; a silent worker is told apart
 * from a dead one with {@see isDead()}.
 *
 * @internal
 */
final class MutantWorker
{
    private const int SELECT_TIMEOUT_MICROS = 100_000;

    private const int STDERR_TAIL_BYTES = 16_384;

    // A continuous writer must not prevent the parent from checking its deadline.
    private const int STDERR_DRAIN_BYTES = 65_536;

    /** @var closed-resource|resource */
    private readonly mixed $stdin;

    /** @var closed-resource|resource */
    private readonly mixed $stdout;

    /** @var closed-resource|resource */
    private readonly mixed $stderr;

    private string $readBuffer = '';

    private string $stderrTail = '';

    /** Wall-clock deadline of the frame in flight, null when idle. */
    private ?float $deadline = null;

    /**
     * @param closed-resource|resource $process
     * @param array<int, resource>     $pipes
     */
    private function __construct(private readonly mixed $process, array $pipes)
    {
        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];
        stream_set_blocking($this->stdout, false);
        stream_set_blocking($this->stderr, false);
    }

    /**
     * @param list<string> $command the full argv, interpreter first
     */
    public static function spawn(array $command): self
    {
        $pipes = [];
        $process = @proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to spawn the Phel mutation worker.');
        }

        return new self($process, $pipes);
    }

    /**
     * Send `$frame` and block until the worker answers or `$timeoutSeconds`
     * pass. Returns the decoded answer, or null when the deadline passed
     * or the worker died first.
     *
     * @param array<string, mixed> $frame
     *
     * @return array<string, mixed>|null
     */
    public function request(array $frame, float $timeoutSeconds): ?array
    {
        $this->send($frame, $timeoutSeconds);

        while (true) {
            $answer = $this->tryReceive();
            if ($answer !== null) {
                return $answer;
            }

            if ($this->isDead() || $this->isOverdue()) {
                $this->deadline = null;

                return null;
            }

            $this->waitForOutput();
        }
    }

    /**
     * Send `$frame` without waiting; the answer arrives through
     * {@see tryReceive()} and the worker is overdue once `$timeoutSeconds`
     * pass without one.
     *
     * @param array<string, mixed> $frame
     */
    public function send(array $frame, float $timeoutSeconds): void
    {
        $this->deadline = microtime(true) + $timeoutSeconds;
        $this->writeAll(WorkerFrame::encode($frame));
    }

    /**
     * The next complete answer, or null when none is buffered yet.
     *
     * @return array<string, mixed>|null
     */
    public function tryReceive(): ?array
    {
        $this->drainStderr();
        $answer = $this->tryReadFrame();
        if ($answer !== null) {
            $this->deadline = null;
        }

        return $answer;
    }

    public function isBusy(): bool
    {
        return $this->deadline !== null;
    }

    public function isOverdue(): bool
    {
        return $this->deadline !== null && microtime(true) >= $this->deadline;
    }

    /**
     * Dead and with nothing left to read: a crash, not a slow answer.
     */
    public function isDead(): bool
    {
        return !$this->isAlive() && $this->tryReadFrame() === null;
    }

    public function isAlive(): bool
    {
        if (!is_resource($this->process)) {
            return false;
        }

        $status = @proc_get_status($this->process);

        return $status['running'];
    }

    /**
     * Block for at most one poll interval until this worker has output.
     */
    public function waitForOutput(): void
    {
        $reads = [$this->stdout];
        if (is_resource($this->stderr) && !feof($this->stderr)) {
            $reads[] = $this->stderr;
        }

        $writes = null;
        $exceptions = null;
        /** @psalm-suppress InvalidArgument */
        @stream_select($reads, $writes, $exceptions, 0, self::SELECT_TIMEOUT_MICROS);
        $this->drainStderr();
    }

    public function readStderr(): string
    {
        $this->drainStderr();
        $out = $this->stderrTail;
        $this->stderrTail = '';

        return $out;
    }

    public function terminate(): void
    {
        if (is_resource($this->stdin)) {
            /** @psalm-suppress InaccessibleProperty */
            @fclose($this->stdin);
        }

        if (is_resource($this->process)) {
            $deadline = microtime(true) + 0.2;
            while (microtime(true) < $deadline && $this->isAlive()) {
                usleep(10_000);
            }

            if ($this->isAlive()) {
                @proc_terminate($this->process, 9);
            }

            @proc_close($this->process);
        }

        foreach ([$this->stdout, $this->stderr] as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
    }

    private function drainStderr(): void
    {
        if (!is_resource($this->stderr)) {
            return;
        }

        $remaining = self::STDERR_DRAIN_BYTES;
        while ($remaining > 0) {
            $chunk = @fread($this->stderr, min(8192, $remaining));
            if ($chunk === false || $chunk === '') {
                return;
            }

            $this->stderrTail = substr($this->stderrTail . $chunk, -self::STDERR_TAIL_BYTES);
            $remaining -= strlen($chunk);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryReadFrame(): ?array
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

        if (preg_match(sprintf('/^[0-9a-f]{%d}\n/', $headerSize - 1), $this->readBuffer) !== 1) {
            throw new RuntimeException(
                'The mutation worker wrote output that is not a frame: ' . substr($this->readBuffer, 0, 200),
            );
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

    private function writeAll(string $data): void
    {
        while ($data !== '') {
            /** @psalm-suppress PossiblyInvalidArgument */
            $written = @fwrite($this->stdin, $data);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Failed to write to the mutation worker.');
            }

            $data = substr($data, $written);
        }
    }
}
