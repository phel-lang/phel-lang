<?php

declare(strict_types=1);

namespace Phel\Fiber;

use Gacela\Framework\AbstractFacade;
use Phel\Fiber\Domain\Awaitable;
use Phel\Fiber\Domain\Future;
use Phel\Fiber\Domain\Promise;
use Phel\Fiber\Domain\Scheduler;

/**
 * Public entrypoint for the Fiber module. Exposes the cooperative async
 * primitives used by Phel's async library: {@see Promise}, {@see Future},
 * and the shared {@see Scheduler}.
 *
 * @extends AbstractFacade<FiberFactory>
 */
final class FiberFacade extends AbstractFacade implements FiberFacadeInterface
{
    public function createPromise(): Promise
    {
        return $this->getFactory()->createPromise();
    }

    /**
     * @param callable():mixed $body
     */
    public function future(callable $body): Future
    {
        return $this->getFactory()->createFuture($body);
    }

    public function await(Awaitable $awaitable, ?int $timeoutMs = null): mixed
    {
        return $this->getFactory()->await($awaitable, $timeoutMs);
    }

    public function scheduler(): Scheduler
    {
        return $this->getFactory()->scheduler();
    }
}
