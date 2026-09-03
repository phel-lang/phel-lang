<?php

declare(strict_types=1);

namespace Phel\Mutate\Application;

use Phel\Shared\Process\WorkerFrame;
use Phel\Shared\Process\WorkerProcess;
use RuntimeException;

use function microtime;

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

    /** Wall-clock deadline of the frame in flight, null when idle. */
    private ?float $deadline = null;

    private function __construct(
        private readonly WorkerProcess $process,
    ) {}

    /**
     * @param list<string> $command the full argv, interpreter first
     */
    public static function spawn(array $command): self
    {
        $process = WorkerProcess::open($command, 'Failed to write to the mutation worker.');
        if (!$process instanceof WorkerProcess) {
            throw new RuntimeException('Failed to spawn the Phel mutation worker.');
        }

        return new self($process);
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
        $this->process->write(WorkerFrame::encode($frame));
    }

    /**
     * The next complete answer, or null when none is buffered yet.
     *
     * @return array<string, mixed>|null
     */
    public function tryReceive(): ?array
    {
        $answer = $this->process->tryReadFrame();
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
        return !$this->isAlive() && $this->process->tryReadFrame() === null;
    }

    public function isAlive(): bool
    {
        return $this->process->isAlive();
    }

    /**
     * Block for at most one poll interval until this worker has output.
     */
    public function waitForOutput(): void
    {
        $this->process->waitForOutput(self::SELECT_TIMEOUT_MICROS);
    }

    public function readStderr(): string
    {
        return $this->process->readStderr();
    }

    public function terminate(): void
    {
        $this->process->terminate(9);
    }
}
