<?php

declare(strict_types=1);

namespace PhelTest\Unit\Fiber\Domain;

use Fiber;
use Phel\Fiber\Domain\Promise;
use Phel\Fiber\Domain\Scheduler;
use PHPUnit\Framework\TestCase;

final class PromiseTest extends TestCase
{
    private Scheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new Scheduler();
        $this->scheduler->setSleepMicroseconds(50);
    }

    public function test_it_starts_unrealized(): void
    {
        $promise = new Promise($this->scheduler);

        self::assertFalse($promise->isRealized());
    }

    public function test_it_delivers_a_value_and_marks_realized(): void
    {
        $promise = new Promise($this->scheduler);

        self::assertTrue($promise->deliver(42));
        self::assertTrue($promise->isRealized());
        self::assertSame(42, $promise->value());
    }

    public function test_it_ignores_subsequent_deliveries(): void
    {
        $promise = new Promise($this->scheduler);
        $promise->deliver('first');

        self::assertFalse($promise->deliver('second'));
        self::assertSame('first', $promise->value());
    }

    public function test_deref_returns_value_when_already_delivered(): void
    {
        $promise = new Promise($this->scheduler);
        $promise->deliver('done');

        self::assertSame('done', $promise->deref());
    }

    public function test_deref_with_timeout_returns_fallback_immediately_for_zero(): void
    {
        $promise = new Promise($this->scheduler);

        self::assertSame(':fallback', $promise->derefWithTimeout(0, ':fallback'));
        self::assertFalse($promise->isRealized());
    }

    public function test_deref_with_timeout_returns_value_when_already_delivered(): void
    {
        $promise = new Promise($this->scheduler);
        $promise->deliver(7);

        self::assertSame(7, $promise->derefWithTimeout(1000, ':fallback'));
    }

    public function test_deref_with_timeout_returns_fallback_when_unrealized(): void
    {
        $promise = new Promise($this->scheduler);

        $start = microtime(true);
        $result = $promise->derefWithTimeout(10, ':timeout');
        $elapsed = microtime(true) - $start;

        self::assertSame(':timeout', $result);
        self::assertGreaterThan(0.005, $elapsed);
        self::assertLessThan(0.5, $elapsed);
    }

    public function test_deliver_preserves_null_as_a_valid_value(): void
    {
        $promise = new Promise($this->scheduler);

        self::assertTrue($promise->deliver(null));
        self::assertTrue($promise->isRealized());
        self::assertNull($promise->value());
        self::assertFalse($promise->deliver('other'));
    }

    public function test_second_deliver_does_not_overwrite_the_stored_value(): void
    {
        $promise = new Promise($this->scheduler);
        $promise->deliver('first');
        $promise->deliver('second');
        $promise->deliver('third');

        self::assertSame('first', $promise->deref());
    }

    public function test_deref_with_negative_timeout_returns_fallback_immediately(): void
    {
        $promise = new Promise($this->scheduler);

        self::assertSame(':fallback', $promise->derefWithTimeout(-1, ':fallback'));
        self::assertFalse($promise->isRealized());
    }

    public function test_deref_with_timeout_zero_returns_value_when_already_delivered(): void
    {
        $promise = new Promise($this->scheduler);
        $promise->deliver('ready');

        self::assertSame('ready', $promise->derefWithTimeout(0, ':fallback'));
    }

    public function test_deref_from_fiber_unblocks_when_another_fiber_delivers(): void
    {
        $promise = new Promise($this->scheduler);
        $waiter = new Fiber(static function () use ($promise, &$observed): void {
            $observed = $promise->deref();
        });
        $waiter->start();

        $this->scheduler->enqueue($waiter);

        $producer = new Fiber(static function () use ($promise): void {
            $promise->deliver('handoff');
        });
        $this->scheduler->enqueue($producer);
        $this->scheduler->runUntilIdle();

        self::assertSame('handoff', $observed);
        self::assertTrue($promise->isRealized());
    }

    public function test_many_deliveries_leave_first_value_and_return_false_each_time(): void
    {
        $promise = new Promise($this->scheduler);
        $promise->deliver(1);

        self::assertFalse($promise->deliver(2));
        self::assertFalse($promise->deliver(3));
        self::assertFalse($promise->deliver(4));
        self::assertSame(1, $promise->value());
    }
}
