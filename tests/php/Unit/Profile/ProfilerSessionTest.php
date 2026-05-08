<?php

declare(strict_types=1);

namespace PhelTest\Unit\Profile;

use Phel\Lang\AbstractFn;
use Phel\Profile\Domain\ProfilerSession;
use PHPUnit\Framework\TestCase;

final class ProfilerSessionTest extends TestCase
{
    public function test_records_per_fn_call_count_and_inclusive_time(): void
    {
        $session = new ProfilerSession();

        $start = $session->enter('user/foo');
        usleep(1_000);
        $session->exit('user/foo', $start);

        $report = $session->stop();
        $stats = $report->fnStats();

        self::assertArrayHasKey('user/foo', $stats);
        self::assertSame(1, $stats['user/foo']['calls']);
        self::assertGreaterThan(0, $stats['user/foo']['totalNs']);
        self::assertSame($stats['user/foo']['selfNs'], $stats['user/foo']['totalNs']);
    }

    public function test_self_time_subtracts_nested_child_time(): void
    {
        $session = new ProfilerSession();

        $outer = $session->enter('parent');
        usleep(500);
        $inner = $session->enter('child');
        usleep(2_000);
        $session->exit('child', $inner);
        usleep(500);
        $session->exit('parent', $outer);

        $stats = $session->stop()->fnStats();

        self::assertLessThan($stats['parent']['totalNs'], $stats['parent']['selfNs']);
        self::assertSame($stats['child']['totalNs'], $stats['child']['selfNs']);
    }

    public function test_aggregates_repeated_calls(): void
    {
        $session = new ProfilerSession();

        for ($i = 0; $i < 5; ++$i) {
            $token = $session->enter('user/loop');
            $session->exit('user/loop', $token);
        }

        $stats = $session->stop()->fnStats();

        self::assertSame(5, $stats['user/loop']['calls']);
        self::assertGreaterThan(0, $stats['user/loop']['maxNs']);
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
}
