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
 * One `phel _mutate-worker` subprocess seen from the parent: send a frame,
 * wait for the answer up to a deadline. The subprocess exists so that a
 * mutant which never terminates or crashes the interpreter costs one
 * worker, not the run; {@see request()} reports both as `null` and the
 * caller checks {@see isAlive()} to tell them apart.
 *
 * @internal
 */
final class MutantWorker
{
    private const int SELECT_TIMEOUT_MICROS = 100_000;

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
        $this->writeAll(WorkerFrame::encode($frame));

        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            $answer = $this->tryReadFrame();
            if ($answer !== null) {
                return $answer;
            }

            if (!$this->isAlive() && $this->tryReadFrame() === null) {
                return null;
            }

            if (microtime(true) >= $deadline) {
                return null;
            }

            $reads = [$this->stdout];
            $writes = null;
            $exceptions = null;
            /** @psalm-suppress InvalidArgument */
            @stream_select($reads, $writes, $exceptions, 0, self::SELECT_TIMEOUT_MICROS);
        }
    }

    public function isAlive(): bool
    {
        if (!is_resource($this->process)) {
            return false;
        }

        $status = @proc_get_status($this->process);

        return $status['running'];
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
