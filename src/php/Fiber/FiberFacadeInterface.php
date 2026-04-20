<?php

declare(strict_types=1);

namespace Phel\Fiber;

use Phel\Fiber\Domain\Awaitable;
use Phel\Fiber\Domain\Future;
use Phel\Fiber\Domain\Promise;
use Phel\Fiber\Domain\Scheduler;

interface FiberFacadeInterface
{
    public function createPromise(): Promise;

    /**
     * @param callable():mixed $body
     */
    public function future(callable $body): Future;

    public function await(Awaitable $awaitable, ?int $timeoutMs = null): mixed;

    public function scheduler(): Scheduler;
}
