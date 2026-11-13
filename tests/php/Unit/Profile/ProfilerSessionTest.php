<?php

declare(strict_types=1);

namespace PhelTest\Unit\Profile;

use Fiber;
use Phel;
use Phel\Lang\AbstractFn;
use Phel\Lang\Keyword;
use Phel\Profile\Domain\ProfilerSession;
use PHPUnit\Framework\TestCase;

final class ProfilerSessionTest extends TestCase
{
    public function test_records_per_fn_call_count_and_inclusive_time(): void
    {
        $session = new ProfilerSession();

        $session->enter('user/foo');
        usleep(1_000);
        $session->exit();

        $stats = $session->stop()->fnStats();

        self::assertArrayHasKey('user/foo', $stats);
        self::assertSame(1, $stats['user/foo']['calls']);
        self::assertGreaterThan(0, $stats['user/foo']['totalNs']);
        self::assertSame($stats['user/foo']['selfNs'], $stats['user/foo']['totalNs']);
    }

    public function test_self_time_subtracts_nested_child_time(): void
    {
        $session = new ProfilerSession();

        $session->enter('parent');
        usleep(500);
        $session->enter('child');
        usleep(2_000);
        $session->exit();
        usleep(500);
        $session->exit();

        $stats = $session->stop()->fnStats();

        self::assertLessThan($stats['parent']['totalNs'], $stats['parent']['selfNs']);
        self::assertSame($stats['child']['totalNs'], $stats['child']['selfNs']);
    }

    public function test_aggregates_repeated_calls(): void
    {
        $session = new ProfilerSession();

        for ($i = 0; $i < 5; ++$i) {
            $session->enter('user/loop');
            $session->exit();
        }

        $stats = $session->stop()->fnStats();

        self::assertSame(5, $stats['user/loop']['calls']);
        self::assertGreaterThan(0, $stats['user/loop']['maxNs']);
    }

    public function test_interleaved_fibers_keep_their_own_call_stacks(): void
    {
        $session = new ProfilerSession();
        $fiberA = new Fiber(static function () use ($session): void {
            $session->enter('audit/a');
            Fiber::suspend();
            $session->exit();
        });
        $fiberB = new Fiber(static function () use ($session): void {
            $session->enter('audit/b');
            Fiber::suspend();
            $session->exit();
        });

        $fiberA->start();
        $fiberB->start();
        $fiberA->resume();

        self::assertSame(['audit/a'], array_keys($session->stop()->fnStats()));

        $fiberB->resume();
        $stats = $session->stop()->fnStats();

        self::assertSame(1, $stats['audit/a']['calls']);
        self::assertSame(1, $stats['audit/b']['calls']);
    }

    public function test_a_fiber_frame_does_not_count_as_a_main_child(): void
    {
        $session = new ProfilerSession();
        $session->enter('outer');

        $fiber = new Fiber(static function () use ($session): void {
            $session->enter('inner');
            Fiber::suspend();
            $session->exit();
        });
        $fiber->start();

        $session->exit();
        $fiber->resume();

        $stats = $session->stop()->fnStats();
        self::assertSame(1, $stats['outer']['calls']);
        self::assertSame(1, $stats['inner']['calls']);
        self::assertSame($stats['outer']['totalNs'], $stats['outer']['selfNs']);
    }

    public function test_records_compile_phase_durations(): void
    {
        $session = new ProfilerSession();
        $session->recordPhase('lex', 'a.phel', 1.5);
        $session->recordPhase('lex', 'a.phel', 0.5);
        $session->recordPhase('parse', 'a.phel', 2.0);

        $phases = $session->stop()->phaseMs();

        self::assertSame(2.0, $phases['a.phel']['lex']);
        self::assertSame(2.0, $phases['a.phel']['parse']);
    }

    public function test_wrap_fn_is_idempotent(): void
    {
        $session = new ProfilerSession();
        $fn = new class() extends AbstractFn {
            public const string BOUND_TO = 'user\\f';

            public function __invoke(mixed ...$args): mixed
            {
                return null;
            }
        };

        $wrapped = $session->wrapFn($fn);
        $rewrapped = $session->wrapFn($wrapped);

        self::assertSame($wrapped, $rewrapped);
    }

    public function test_wrap_fn_copies_the_inner_metadata(): void
    {
        $session = new ProfilerSession();
        $meta = Phel::map(Keyword::create('doc'), 'profiled');
        $fn = (new class() extends AbstractFn {
            public function __invoke(mixed ...$args): mixed
            {
                return null;
            }
        })->withMeta($meta);

        $wrapped = $session->wrapFn($fn);

        self::assertSame($meta, $wrapped->getMeta());
    }
}
