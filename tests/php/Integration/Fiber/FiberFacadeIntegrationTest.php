<?php

declare(strict_types=1);

namespace PhelTest\Integration\Fiber;

use Fiber;
use Phel\Fiber\Domain\Scheduler;
use Phel\Fiber\FiberFacade;
use PHPUnit\Framework\TestCase;

use function microtime;

final class FiberFacadeIntegrationTest extends TestCase
{
    private FiberFacade $facade;

    protected function setUp(): void
    {
        $scheduler = new Scheduler();
        $scheduler->setSleepMicroseconds(50);
        Scheduler::setInstance($scheduler);
        $this->facade = new FiberFacade();
    }

    protected function tearDown(): void
    {
        Scheduler::setInstance(null);
    }

    public function test_three_cooperating_fibers_pass_values_through_promises(): void
    {
        $start = microtime(true);

        $stage1 = $this->facade->createPromise();
        $stage2 = $this->facade->createPromise();
        $final = $this->facade->createPromise();

        // Producer: deliver the initial value.
        $this->facade->future(static function () use ($stage1): int {
            $stage1->deliver(1);
            return 0;
        });

        // Transformer 1: multiply by 10 then hand off.
        $this->facade->future(static function () use ($stage1, $stage2): int {
            /** @var int $value */
            $value = $stage1->deref();
            $stage2->deliver($value * 10);
            return 0;
        });

        // Transformer 2: add 5 then finalize.
        $this->facade->future(static function () use ($stage2, $final): int {
            /** @var int $value */
            $value = $stage2->deref();
            $final->deliver($value + 5);
            return 0;
        });

        $result = $final->deref();

        self::assertSame(15, $result);
        self::assertLessThan(0.5, microtime(true) - $start);
    }

    public function test_facade_await_blocks_until_future_resolves(): void
    {
        $future = $this->facade->future(static function (): string {
            Fiber::suspend();
            return 'ok';
        });

        self::assertSame('ok', $this->facade->await($future));
    }

    public function test_facade_await_with_timeout_returns_null_on_unresolved(): void
    {
        $parked = $this->facade->createPromise();
        $slow = $this->facade->future(static fn(): mixed => $parked->deref());

        self::assertNull($this->facade->await($slow, 5));
    }
}
