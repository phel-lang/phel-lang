<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\Process\WorkerProcess;
use RuntimeException;

/**
 * One live worker subprocess. Owns the {@see WorkerProcess} handle and the
 * assignment it is currently working on. The orchestrator drives many of
 * these via {@see stream_select}; this class exposes minimal primitives so
 * the polling logic stays in one place.
 *
 * @internal
 */
final class TestWorkerHandle
{
    private ?int $assignedIndex = null;

    private ?string $assignedNamespace = null;

    private function __construct(
        private readonly WorkerProcess $process,
    ) {}

    /**
     * @param list<string> $opcacheFlags `-d` flags spliced before the script so
     *                                   the pool shares one OPcache file cache
     */
    public static function spawn(string $phpBinary, string $phelBinary, array $opcacheFlags = []): self
    {
        $process = WorkerProcess::open(
            self::buildCommand($phpBinary, $phelBinary, $opcacheFlags),
            'Failed to write to worker stdin.',
        );

        if (!$process instanceof WorkerProcess) {
            throw new RuntimeException('Failed to spawn Phel test worker.');
        }

        return new self($process);
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
        return $this->process->stdoutHandle();
    }

    public function isIdle(): bool
    {
        return $this->assignedIndex === null;
    }

    public function isAlive(): bool
    {
        return $this->process->isAlive();
    }

    public function assign(int $index, string $namespace, string $frame): void
    {
        $this->assignedIndex = $index;
        $this->assignedNamespace = $namespace;
        $this->process->write($frame);
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
        return $this->process->tryReadFrame();
    }

    public function readStderrNonBlocking(): string
    {
        return $this->process->readStderr();
    }

    public function terminate(): void
    {
        $this->process->terminate();
    }
}
