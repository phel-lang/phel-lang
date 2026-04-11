<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Phel\Lang\PhelFuture;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use RuntimeException;

use function Amp\async;

final class PhelFutureTest extends TestCase
{
    public function test_is_realized_returns_false_for_incomplete_future(): void
    {
        $deferred = new DeferredFuture();
        $phelFuture = new PhelFuture($deferred->getFuture(), new DeferredCancellation());

        self::assertFalse($phelFuture->isRealized());
    }

    public function test_is_realized_returns_true_for_complete_future(): void
    {
        $phelFuture = new PhelFuture(Future::complete(42), new DeferredCancellation());

        self::assertTrue($phelFuture->isRealized());
    }

    public function test_deref_returns_underlying_value(): void
    {
        $phelFuture = new PhelFuture(Future::complete('hello'), new DeferredCancellation());

        self::assertSame('hello', $phelFuture->deref());
    }

    public function test_deref_propagates_underlying_exception(): void
    {
        $phelFuture = new PhelFuture(
            Future::error(new RuntimeException('boom')),
            new DeferredCancellation(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $phelFuture->deref();
    }

    public function test_unwrap_returns_inner_future(): void
    {
        $inner = Future::complete(1);
        $phelFuture = new PhelFuture($inner, new DeferredCancellation());

        self::assertSame($inner, $phelFuture->unwrap());
    }

    public function test_deref_with_timeout_returns_value_when_future_completes_in_time(): void
    {
        $phelFuture = new PhelFuture(Future::complete(42), new DeferredCancellation());

        self::assertSame(42, $phelFuture->derefWithTimeout(1000, 'fallback'));
    }

    public function test_deref_with_timeout_zero_returns_fallback_immediately(): void
    {
        $phelFuture = new PhelFuture(Future::complete(42), new DeferredCancellation());

        self::assertSame('fallback', $phelFuture->derefWithTimeout(0, 'fallback'));
    }

    public function test_deref_with_timeout_negative_returns_fallback_immediately(): void
    {
        $phelFuture = new PhelFuture(Future::complete(42), new DeferredCancellation());

        self::assertSame('fallback', $phelFuture->derefWithTimeout(-1, 'fallback'));
    }

    public function test_is_cancelled_false_initially(): void
    {
        $phelFuture = new PhelFuture(Future::complete(1), new DeferredCancellation());

        self::assertFalse($phelFuture->isCancelled());
    }

    public function test_cancel_sets_cancelled_flag(): void
    {
        $deferred = new DeferredFuture();
        $phelFuture = new PhelFuture($deferred->getFuture(), new DeferredCancellation());

        $phelFuture->cancel();

        self::assertTrue($phelFuture->isCancelled());
    }

    public function test_cancel_is_idempotent(): void
    {
        $deferred = new DeferredFuture();
        $phelFuture = new PhelFuture($deferred->getFuture(), new DeferredCancellation());

        $phelFuture->cancel();
        $phelFuture->cancel();

        self::assertTrue($phelFuture->isCancelled());
    }

    public function test_is_realized_true_when_cancelled(): void
    {
        $deferred = new DeferredFuture();
        $phelFuture = new PhelFuture($deferred->getFuture(), new DeferredCancellation());

        self::assertFalse($phelFuture->isRealized());

        $phelFuture->cancel();

        self::assertTrue($phelFuture->isRealized());
    }

    public function test_deref_with_timeout_returns_fallback_when_cancelled(): void
    {
        $deferred = new DeferredFuture();
        $phelFuture = new PhelFuture($deferred->getFuture(), new DeferredCancellation());

        $phelFuture->cancel();

        self::assertSame('fallback', $phelFuture->derefWithTimeout(1000, 'fallback'));
    }

    public function test_deref_with_timeout_returns_fallback_when_future_is_slow(): void
    {
        // Run inside a fiber with a referenced tick so the event loop stays alive
        // long enough for the TimeoutCancellation (which is internally unreferenced)
        // to fire and cancel the await.
        $keepAliveId = EventLoop::delay(1.0, static fn(): null => null);

        try {
            $result = async(static function (): mixed {
                $deferred = new DeferredFuture();
                $phelFuture = new PhelFuture($deferred->getFuture(), new DeferredCancellation());
                return $phelFuture->derefWithTimeout(10, 'timed-out');
            })->await();

            self::assertSame('timed-out', $result);
        } finally {
            EventLoop::cancel($keepAliveId);
        }
    }
}
