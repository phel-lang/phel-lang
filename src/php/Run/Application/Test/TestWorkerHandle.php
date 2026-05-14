<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use RuntimeException;

use function fclose;
use function fread;
use function fwrite;
use function is_resource;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_set_blocking;
use function strlen;
use function usleep;

/**
 * One live worker subprocess. Owns the proc_open resource and its
 * stdin/stdout/stderr pipes. The orchestrator drives many of these
 * via {@see stream_select}; this class exposes minimal primitives so
 * the polling logic stays in one place.
 */
final class TestWorkerHandle
{
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
     * @param array<int, resource> $pipes
     */
    public function __construct(private readonly mixed $process, array $pipes)
    {
        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        stream_set_blocking($this->stdout, false);
        stream_set_blocking($this->stderr, false);
    }

    public static function spawn(string $phpBinary, string $phelBinary): self
    {
        $cmd = [$phpBinary, $phelBinary, '_test-worker'];

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
        return $status['running'];
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
     * if a full frame is not yet available; throws when the worker has
     * exited mid-frame.
     *
     * @return array<string, mixed>|null
     */
    public function tryReadFrame(): ?array
    {
        /** @psalm-suppress PossiblyInvalidArgument */
        $chunk = @fread($this->stdout, 65_536);
        if ($chunk === false) {
            return null;
        }

        if ($chunk !== '') {
            $this->readBuffer .= $chunk;
        }

        $headerSize = WorkerFrame::headerSize();
        if (strlen($this->readBuffer) < $headerSize) {
            return null;
        }

        $hex = substr($this->readBuffer, 0, $headerSize - 1);
        $length = (int) hexdec($hex);
        $total = $headerSize + $length;

        if (strlen($this->readBuffer) < $total) {
            return null;
        }

        $body = substr($this->readBuffer, $headerSize, $length);
        $this->readBuffer = substr($this->readBuffer, $total);

        return WorkerFrame::decodeBody($body);
    }

    public function readStderrNonBlocking(): string
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
