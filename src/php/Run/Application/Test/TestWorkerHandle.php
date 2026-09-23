<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\Process\WorkerFrame;
use RuntimeException;

use function fclose;
use function fread;
use function fwrite;
use function implode;
use function is_resource;
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
    private const int STDERR_TAIL_BYTES = 16_384;

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

    /**
     * @param closed-resource|resource $process
     * @param array<int, resource>     $pipes
     */
    public function __construct(private readonly mixed $process, array $pipes)
    {
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
        $stdout = trim($this->readBuffer);
        if ($stdout !== '') {
            $parts[] = 'stdout: ' . $stdout;
        }

        $stderr = trim($this->stderrTail);
        if ($stderr !== '') {
            $parts[] = 'stderr: ' . $stderr;
        }

        return implode("\n", $parts);
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
        $chunk = $this->readStderrNonBlocking();
        if ($chunk === '') {
            return;
        }

        $this->stderrTail = substr($this->stderrTail . $chunk, -self::STDERR_TAIL_BYTES);
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

        if (is_resource($this->process)) {
            $deadline = microtime(true) + 0.2;
            while (microtime(true) < $deadline) {
                $status = @proc_get_status($this->process);
                if (!$status['running']) {
                    break;
                }

                usleep(10_000);
            }

            $status = @proc_get_status($this->process);
            if ($status['running']) {
                @proc_terminate($this->process);
            }

            @proc_close($this->process);
        }

        foreach ([$this->stdout, $this->stderr] as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
    }

    private function readStderrNonBlocking(): string
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

    private function pumpReadBuffer(): void
    {
        /** @psalm-suppress PossiblyInvalidArgument */
        $chunk = @fread($this->stdout, 65_536);
        if ($chunk === false || $chunk === '') {
            return;
        }

        $this->readBuffer .= $chunk;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractFrame(): ?array
    {
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
