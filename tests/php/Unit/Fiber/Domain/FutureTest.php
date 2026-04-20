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
}
