<?php

declare(strict_types=1);

namespace PhelTest\Unit\Fiber\Domain;

use Fiber;
use Phel\Fiber\Domain\Promise;
use Phel\Fiber\Domain\Scheduler;
use PHPUnit\Framework\TestCase;

final class SchedulerTest extends TestCase
{
    public function test_ready_queue_starts_empty(): void
    {
        $scheduler = new Scheduler();

        self::assertFalse($scheduler->hasReady());
        self::assertSame(0, $scheduler->readyCount());
    }

    public function test_enqueue_adds_to_ready_queue(): void
    {
        $scheduler = new Scheduler();
        $fiber = new Fiber(static function (): void {
            Fiber::suspend();
        });
        $fiber->start();

        $scheduler->enqueue($fiber);

        self::assertTrue($scheduler->hasReady());
        self::assertSame(1, $scheduler->readyCount());
    }

    public function test_enqueue_drops_terminated_fibers(): void
    {
        $scheduler = new Scheduler();
        $fiber = new Fiber(static function (): void {});
        $fiber->start();

        $scheduler->enqueue($fiber);

        self::assertFalse($scheduler->hasReady());
    }

    public function test_tick_advances_one_fiber_in_fifo_order(): void
    {
        $scheduler = new Scheduler();
        $order = [];

        $first = new Fiber(static function () use (&$order): void {
            $order[] = 'a-start';
            Fiber::suspend();
            $order[] = 'a-end';
        });
        $second = new Fiber(static function () use (&$order): void {
            $order[] = 'b-start';
            Fiber::suspend();
            $order[] = 'b-end';
        });

        $first->start();
        $second->start();
        $scheduler->enqueue($first);
        $scheduler->enqueue($second);

        self::assertTrue($scheduler->tick());
        self::assertSame(['a-start', 'b-start', 'a-end'], $order);

        self::assertTrue($scheduler->tick());
        self::assertSame(['a-start', 'b-start', 'a-end', 'b-end'], $order);

        self::assertFalse($scheduler->tick());
    }

    public function test_run_until_idle_drains_cooperating_fibers(): void
    {
        $scheduler = new Scheduler();
        $log = [];

        for ($i = 0; $i < 3; ++$i) {
            $id = 'fiber-' . $i;
            $fiber = new Fiber(static function () use (&$log, $id): void {
                $log[] = $id . ':tick1';
                Fiber::suspend();
                $log[] = $id . ':tick2';
            });
            $fiber->start();
            $scheduler->enqueue($fiber);
        }

        $scheduler->runUntilIdle();

        self::assertSame(
            [
                'fiber-0:tick1',
                'fiber-1:tick1',
                'fiber-2:tick1',
                'fiber-0:tick2',
                'fiber-1:tick2',
                'fiber-2:tick2',
            ],
            $log,
        );
    }

    public function test_await_returns_value_when_already_realized(): void
    {
        $scheduler = new Scheduler();
        $promise = new Promise($scheduler);
        $promise->deliver('ready');

        self::assertSame('ready', $scheduler->await($promise));
    }

    public function test_singleton_round_trips_through_set_instance(): void
    {
        $isolated = new Scheduler();
        Scheduler::setInstance($isolated);

        self::assertSame($isolated, Scheduler::instance());

        Scheduler::setInstance(null);
        self::assertNotSame($isolated, Scheduler::instance());
    }

    public function test_sleep_microseconds_floors_at_zero(): void
    {
        $scheduler = new Scheduler();
        $scheduler->setSleepMicroseconds(-100);

        self::assertSame(0, $scheduler->sleepMicroseconds());
    }
}
