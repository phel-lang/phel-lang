<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Infrastructure;

use Fiber;
use Phel\Nrepl\Infrastructure\ClientFiberPool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClientFiberPoolTest extends TestCase
{
    public function test_add_skips_terminated_fibers(): void
    {
        $pool = new ClientFiberPool();

        $fiber = new Fiber(static fn(): int => 1);
        $fiber->start();

        $pool->add($fiber);

        self::assertSame(0, $pool->count());
    }

    public function test_step_keeps_suspended_fibers_and_drops_terminated_ones(): void
    {
        $pool = new ClientFiberPool();

        $stillAlive = new Fiber(static function (): void {
            Fiber::suspend();
            Fiber::suspend();
        });
        $stillAlive->start();

        $completing = new Fiber(static function (): void {
            Fiber::suspend();
        });
        $completing->start();

        $pool->add($stillAlive);
        $pool->add($completing);
        self::assertSame(2, $pool->count());

        $pool->step();

        self::assertSame(1, $pool->count());
    }

    public function test_step_logs_and_drops_fibers_that_throw(): void
    {
        $messages = [];
        $pool = new ClientFiberPool(static function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        $failing = new Fiber(static function (): never {
            Fiber::suspend();
            throw new RuntimeException('boom');
        });
        $failing->start();

        $pool->add($failing);
        $pool->step();

        self::assertSame(0, $pool->count());
        self::assertCount(1, $messages);
        self::assertStringContainsString('boom', $messages[0]);
    }

    public function test_step_without_logger_swallows_exceptions(): void
    {
        $pool = new ClientFiberPool();

        $failing = new Fiber(static function (): never {
            Fiber::suspend();
            throw new RuntimeException('silent');
        });
        $failing->start();

        $pool->add($failing);
        $pool->step();

        self::assertSame(0, $pool->count());
    }
}
