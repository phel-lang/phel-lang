<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Amp\DeferredFuture;
use Amp\Future;
use Phel\Lang\PhelFuture;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PhelFutureTest extends TestCase
{
    public function test_is_realized_returns_false_for_incomplete_future(): void
    {
        $deferred = new DeferredFuture();
        $phelFuture = new PhelFuture($deferred->getFuture());

        self::assertFalse($phelFuture->isRealized());
    }

    public function test_is_realized_returns_true_for_complete_future(): void
    {
        $phelFuture = new PhelFuture(Future::complete(42));

        self::assertTrue($phelFuture->isRealized());
    }

    public function test_deref_returns_underlying_value(): void
    {
        $phelFuture = new PhelFuture(Future::complete('hello'));

        self::assertSame('hello', $phelFuture->deref());
    }

    public function test_deref_propagates_underlying_exception(): void
    {
        $phelFuture = new PhelFuture(Future::error(new RuntimeException('boom')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $phelFuture->deref();
    }

    public function test_unwrap_returns_inner_future(): void
    {
        $inner = Future::complete(1);
        $phelFuture = new PhelFuture($inner);

        self::assertSame($inner, $phelFuture->unwrap());
    }
}
