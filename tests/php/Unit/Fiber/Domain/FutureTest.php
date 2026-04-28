<?php

declare(strict_types=1);

namespace PhelTest\Unit\Fiber\Domain;

use Fiber;
use Phel\Fiber\Domain\Future;
use Phel\Fiber\Domain\Promise;
use Phel\Fiber\Domain\Scheduler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FutureTest extends TestCase
{
    private Scheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new Scheduler();
        $this->scheduler->setSleepMicroseconds(50);
    }

    public function test_it_runs_the_body_and_returns_the_value(): void
    {
        $future = new Future($this->scheduler, static fn(): int => 42);

        self::assertSame(42, $future->deref());
        self::assertTrue($future->isRealized());
    }

    public function test_it_re_raises_exceptions_from_the_body(): void
    {
        $future = new Future($this->scheduler, static function (): never {
            throw new RuntimeException('boom');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $future->deref();
    }

    public function test_cancellation_flag_is_respected_by_the_body(): void
    {
        $future = null;
        $body = static function () use (&$future): string {
            /** @var Future $future */
            for ($i = 0; $i < 10; ++$i) {
                if ($future->isCancelled()) {
                    return 'cancelled';
                }

                Fiber::suspend();
            }

            return 'completed';
        };

        $future = new Future($this->scheduler, $body);

        // Let the fiber run its first cycle, then cancel before deref.
        $this->scheduler->tick();
        $future->cancel();

        self::assertSame('cancelled', $future->deref());
        self::assertTrue($future->isCancelled());
        self::assertTrue($future->isDone());
    }

    public function test_is_done_is_true_after_cancellation(): void
    {
        $future = new Future($this->scheduler, static function (): string {
            for ($i = 0; $i < 1000; ++$i) {
                Fiber::suspend();
            }

            return 'never';
        });

        self::assertFalse($future->isDone());
        $future->cancel();

        self::assertTrue($future->isDone());
    }

    public function test_deref_with_timeout_returns_fallback_when_still_running(): void
    {
        // Body blocks on a promise that nobody delivers, so the fiber can
        // never realise within the timeout window.
        $parked = new Promise($this->scheduler);
        $future = new Future($this->scheduler, static fn(): mixed => $parked->deref());

        self::assertSame(':timeout', $future->derefWithTimeout(10, ':timeout'));
        self::assertFalse($future->isRealized());
    }

    public function test_multiple_futures_run_cooperatively(): void
    {
        $results = [];
        $futures = [];
        for ($i = 1; $i <= 3; ++$i) {
            $value = $i;
            $futures[] = new Future($this->scheduler, static function () use ($value): int {
                Fiber::suspend();
                return $value * 10;
            });
        }

        foreach ($futures as $future) {
            $results[] = $future->deref();
        }

        self::assertSame([10, 20, 30], $results);
    }

    public function test_is_done_is_false_before_start_and_cancel(): void
    {
        $future = new Future($this->scheduler, static fn(): string => 'x');

        self::assertFalse($future->isCancelled());
        self::assertFalse($future->isDone());
    }

    public function test_deref_with_zero_timeout_returns_fallback_for_unrealized(): void
    {
        $parked = new Promise($this->scheduler);
        $future = new Future($this->scheduler, static fn(): mixed => $parked->deref());

        self::assertSame(':fallback', $future->derefWithTimeout(0, ':fallback'));
        self::assertFalse($future->isRealized());
    }

    public function test_deref_with_timeout_returns_fallback_when_cancelled_before_deliver(): void
    {
        $parked = new Promise($this->scheduler);
        $future = new Future($this->scheduler, static fn(): mixed => $parked->deref());

        $future->cancel();

        self::assertSame(':cancelled', $future->derefWithTimeout(100, ':cancelled'));
    }

    public function test_deref_with_zero_timeout_returns_value_when_already_realized(): void
    {
        $future = new Future($this->scheduler, static fn(): int => 99);

        // Drive the fiber to completion before asking for the value.
        $this->scheduler->runUntilIdle();

        self::assertSame(99, $future->derefWithTimeout(0, ':fallback'));
    }

    public function test_deref_with_timeout_still_rethrows_body_exception(): void
    {
        $future = new Future($this->scheduler, static function (): never {
            throw new RuntimeException('late boom');
        });

        // Let the fiber run so the internal promise is realized with failure.
        $this->scheduler->runUntilIdle();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('late boom');
        $future->derefWithTimeout(100, ':never');
    }

    public function test_cancel_is_idempotent(): void
    {
        $future = new Future($this->scheduler, static fn(): string => 'x');
        $future->cancel();
        $future->cancel();
        $future->cancel();

        self::assertTrue($future->isCancelled());
    }
}
