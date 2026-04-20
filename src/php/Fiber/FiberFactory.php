<?php

declare(strict_types=1);

namespace Phel\Fiber;

use Gacela\Framework\AbstractFactory;
use Phel\Fiber\Domain\Awaitable;
use Phel\Fiber\Domain\Future;
use Phel\Fiber\Domain\Promise;
use Phel\Fiber\Domain\Scheduler;

/**
 * Wires the scheduler and domain objects. The scheduler is process-wide by
 * default (singleton) so that every Promise/Future shares one ready queue;
 * tests that need isolation swap an isolated instance in via
 * {@see Scheduler::setInstance()}.
 *
 * @extends AbstractFactory<FiberConfig>
 */
final class FiberFactory extends AbstractFactory
{
    public function scheduler(): Scheduler
    {
        $scheduler = Scheduler::instance();
        $scheduler->setSleepMicroseconds(FiberConfig::defaultSleepMicroseconds());
        return $scheduler;
    }

    public function createPromise(): Promise
    {
        return new Promise($this->scheduler());
    }

    /**
     * @param callable():mixed $body
     */
    public function createFuture(callable $body): Future
    {
        return new Future($this->scheduler(), $body);
    }

    public function await(Awaitable $awaitable, ?int $timeoutMs = null): mixed
    {
        if ($timeoutMs === null) {
            return $this->scheduler()->await($awaitable);
        }

        return $awaitable->derefWithTimeout($timeoutMs, null);
    }
}
