<?php

declare(strict_types=1);

namespace Phel\Nrepl\Infrastructure;

use Closure;
use Fiber;
use Throwable;

use function count;

/**
 * Cooperative pool of per-client Fibers for the nREPL accept loop.
 *
 * Each connected client runs in its own Fiber. The accept loop calls
 * `step()` once per iteration to resume every fiber that is suspended;
 * fibers that finish or throw are dropped from the pool. Errors are
 * surfaced through the optional logger rather than propagated, so one
 * misbehaving client cannot take down the server.
 */
final class ClientFiberPool
{
    /** @var list<Fiber> */
    private array $fibers = [];

    private readonly ?Closure $errorLogger;

    public function __construct(?callable $errorLogger = null)
    {
        $this->errorLogger = $errorLogger === null ? null : Closure::fromCallable($errorLogger);
    }

    public function add(Fiber $fiber): void
    {
        if (!$fiber->isTerminated()) {
            $this->fibers[] = $fiber;
        }
    }

    public function count(): int
    {
        return count($this->fibers);
    }

    public function step(): void
    {
        $remaining = [];
        foreach ($this->fibers as $fiber) {
            if ($this->advance($fiber)) {
                $remaining[] = $fiber;
            }
        }

        $this->fibers = $remaining;
    }

    /**
     * @return bool true if the fiber should stay in the pool
     */
    private function advance(Fiber $fiber): bool
    {
        if ($fiber->isTerminated()) {
            return false;
        }

        if (!$fiber->isSuspended()) {
            return true;
        }

        try {
            $fiber->resume();
        } catch (Throwable $throwable) {
            if ($this->errorLogger instanceof Closure) {
                ($this->errorLogger)('Client fiber error: ' . $throwable->getMessage());
            }

            return false;
        }

        return $fiber->isSuspended();
    }
}
