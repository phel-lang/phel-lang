<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\Process\WorkerFrame;
use RuntimeException;

use function fclose;
use function fread;
use function fwrite;
use function hexdec;
use function implode;
use function is_resource;
use function microtime;
use function preg_match;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function stream_set_blocking;
use function strlen;
use function substr;
use function trim;
use function usleep;

/**
 * One live worker subprocess. Owns the proc_open resource and its
 * stdin/stdout/stderr pipes. The orchestrator drives many of these
 * via {@see stream_select}; this class exposes minimal primitives so
 * the polling logic stays in one place.
 *
 * @internal
 */
final class TestWorkerHandle
{
    private const int OUTPUT_TAIL_BYTES = 16_384;

    /**
     * Most stderr bytes one drain takes, so a worker writing without pause
     * cannot hold the dispatch loop or grow the parent's memory; the pipe
     * buffers the rest until the next pass.
     */
    private const int STDERR_BYTES_PER_DRAIN = 65_536;

    /** No frame is this large; a header claiming more is stray output. */
    private const int MAX_FRAME_BYTES = 268_435_456;

    /**
     * A worker writes each frame in one call, so a frame still incomplete
     * this long after its first byte is stray output, not a slow write.
     * Measured from the first byte: later bytes do not extend it.
     */
    private const float PARTIAL_FRAME_DEADLINE_SECONDS = 30.0;

    /** How long a worker gets to exit on SIGTERM before it is killed. */
    private const float TERMINATE_GRACE_SECONDS = 1.0;

    private const int SIGKILL = 9;

    /** @var closed-resource|resource */
    private readonly mixed $stdin;

    /** @var closed-resource|resource */
    private readonly mixed $stdout;

    /** @var closed-resource|resource */
    private readonly mixed $stderr;

    private string $readBuffer = '';

    private ?int $assignedIndex = null;

    private ?string $assignedNamespace = null;

    /**
     * How the process ended, captured the first time it is seen dead:
     * `proc_get_status()` reports the real exit code only once.
     */
    private ?string $exitStatus = null;

    /** The most recent stderr output, kept for a crash report. */
    private string $stderrTail = '';

    /** Set once stdout carried bytes that are not a frame; reading stops there. */
    private bool $corruptStdout = false;

    /** When the bytes now in the read buffer started arriving; null when it is empty. */
    private ?float $partialFrameSince = null;

    /**
     * @param closed-resource|resource $process
     * @param array<int, resource>     $pipes
     */
    public function __construct(
        private readonly mixed $process,
        array $pipes,
        private readonly float $partialFrameDeadlineSeconds = self::PARTIAL_FRAME_DEADLINE_SECONDS,
    ) {
        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        stream_set_blocking($this->stdout, false);
        stream_set_blocking($this->stderr, false);
    }

    /**
     * @param list<string> $opcacheFlags `-d` flags spliced before the script so
     *                                   the pool shares one OPcache file cache
     */
    public static function spawn(string $phpBinary, string $phelBinary, array $opcacheFlags = []): self
    {
        $cmd = self::buildCommand($phpBinary, $phelBinary, $opcacheFlags);

        $pipes = [];
        $process = @proc_open(
            $cmd,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to spawn Phel test worker.');
        }

        return new self($process, $pipes);
    }

    /**
     * @param list<string> $opcacheFlags
     *
     * @return list<string>
     */
    public static function buildCommand(string $phpBinary, string $phelBinary, array $opcacheFlags): array
    {
        return [$phpBinary, ...$opcacheFlags, $phelBinary, '_test-worker'];
    }

    /**
     * @return resource
     */
    public function stdoutHandle()
    {
        return $this->stdout;
    }

    public function isIdle(): bool
    {
        return $this->assignedIndex === null;
    }

    public function isAlive(): bool
    {
        if (!is_resource($this->process)) {
            return false;
        }

        $status = @proc_get_status($this->process);
        if (!$status['running'] && $this->exitStatus === null) {
            $this->exitStatus = $status['signaled']
                ? sprintf('killed by signal %d', $status['termsig'])
                : sprintf('exit code %d', $status['exitcode']);
        }

        return $status['running'];
    }

    /**
     * Everything a dead worker left behind, so a crash report says why it
     * died. A PHP fatal error is printed to stdout, not stderr, and often
     * from inside the worker's output buffer, so the unframed stdout tail
     * matters as much as stderr.
     */
    public function crashReport(): string
    {
        $this->pumpReadBuffer();
        $this->drainStderr();

        $parts = [];
        $stdout = trim(substr($this->readBuffer, -self::OUTPUT_TAIL_BYTES));
        if ($stdout !== '') {
            $parts[] = 'stdout: ' . $stdout;
        }

        $stderr = trim($this->stderrTail);
        if ($stderr !== '') {
            $parts[] = 'stderr: ' . $stderr;
        }

        return implode("\n", $parts);
    }

    /**
     * @return bool whether the process has exited
     */
    public function waitForExit(float $seconds): bool
    {
        $deadline = microtime(true) + $seconds;
        while ($this->isAlive()) {
            if (microtime(true) >= $deadline) {
                return false;
            }

            usleep(10_000);
        }

        return true;
    }

    public function exitStatus(): string
    {
        return $this->exitStatus ?? 'exit status unknown';
    }

    public function assign(int $index, string $namespace, string $frame): void
    {
        $this->assignedIndex = $index;
        $this->assignedNamespace = $namespace;
        $this->writeAll($frame);
    }

    public function assignedIndex(): ?int
    {
        return $this->assignedIndex;
    }

    public function assignedNamespace(): ?string
    {
        return $this->assignedNamespace;
    }

    public function clearAssignment(): void
    {
        $this->assignedIndex = null;
        $this->assignedNamespace = null;
    }

    /**
     * Try to read one complete frame off the worker stdout. Returns null
     * if a full frame is not yet buffered.
     *
     * @return array<string, mixed>|null
     */
    public function tryReadFrame(): ?array
    {
        $this->pumpReadBuffer();

        return $this->extractFrame();
    }

    /**
     * Empty the stderr pipe into a bounded tail. Nothing else reads it while
     * the worker lives, and a worker that fills the pipe buffer blocks on its
     * next write and never answers.
     */
    public function drainStderr(): void
    {
        for ($read = 0; $read < self::STDERR_BYTES_PER_DRAIN; $read += strlen($chunk)) {
            /** @psalm-suppress PossiblyInvalidArgument */
            $chunk = @fread($this->stderr, 8192);
            if ($chunk === false || $chunk === '') {
                return;
            }

            $this->stderrTail = substr($this->stderrTail . $chunk, -self::OUTPUT_TAIL_BYTES);
        }
    }

    /**
     * Whether the worker wrote to stdout outside the frame protocol, such as
     * a PHP fatal error flushed from its output buffer. The stream cannot be
     * resynchronised, so the worker is as good as dead.
     */
    public function hasCorruptStdout(): bool
    {
        return $this->corruptStdout
            || ($this->partialFrameSince !== null
                && microtime(true) - $this->partialFrameSince > $this->partialFrameDeadlineSeconds);
    }

    public function closeStdin(): void
    {
        if (is_resource($this->stdin)) {
            /** @psalm-suppress InaccessibleProperty */
            @fclose($this->stdin);
        }
    }

    public function terminate(): void
    {
        $this->closeStdin();

        // A worker that ignores SIGTERM would make proc_close() wait forever,
        // so it is killed once the grace runs out.
        if (is_resource($this->process) && !$this->waitForExit(0.2)) {
            @proc_terminate($this->process);
            if (!$this->waitForExit(self::TERMINATE_GRACE_SECONDS)) {
                @proc_terminate($this->process, self::SIGKILL);
                $this->waitForExit(self::TERMINATE_GRACE_SECONDS);
            }
        }

        foreach ([$this->stdout, $this->stderr] as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }

        if (is_resource($this->process)) {
            @proc_close($this->process);
        }
    }

    private function pumpReadBuffer(): void
    {
        /** @psalm-suppress PossiblyInvalidArgument */
        $chunk = @fread($this->stdout, 65_536);
        if ($chunk === false || $chunk === '') {
            return;
        }

        if ($this->readBuffer === '') {
            $this->partialFrameSince = microtime(true);
        }

        $this->readBuffer .= $chunk;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractFrame(): ?array
    {
        if ($this->corruptStdout || $this->readBuffer === '') {
            return null;
        }

        // Check the header byte by byte as it arrives, so stray output shorter
        // than a header is caught too, not left waiting for more bytes.
        $headerSize = WorkerFrame::headerSize();
        $header = substr($this->readBuffer, 0, $headerSize);
        if (preg_match('/^[0-9a-f]{0,' . ($headerSize - 1) . '}$|^[0-9a-f]{' . ($headerSize - 1) . '}\n$/', $header) !== 1) {
            $this->corruptStdout = true;
            return null;
        }

        if (strlen($header) < $headerSize) {
            return null;
        }

        $length = (int) hexdec(substr($header, 0, $headerSize - 1));
        if ($length > self::MAX_FRAME_BYTES) {
            $this->corruptStdout = true;
            return null;
        }

        $total = $headerSize + $length;
        if (strlen($this->readBuffer) < $total) {
            return null;
        }

        try {
            $frame = WorkerFrame::decodeBody(substr($this->readBuffer, $headerSize, $length));
        } catch (RuntimeException) {
            // Keep the bytes: they are what the crash report shows.
            $this->corruptStdout = true;
            return null;
        }

        $this->readBuffer = substr($this->readBuffer, $total);
        $this->partialFrameSince = $this->readBuffer === '' ? null : microtime(true);

        return $frame;
    }

    private function writeAll(string $data): void
    {
        while ($data !== '') {
            /** @psalm-suppress PossiblyInvalidArgument */
            $written = @fwrite($this->stdin, $data);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Failed to write to worker stdin.');
            }

            $data = substr($data, $written);
        }
    }
}
