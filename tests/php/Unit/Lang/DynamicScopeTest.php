<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Fiber;
use Phel\Lang\DynamicScope;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DynamicScopeTest extends TestCase
{
    protected function setUp(): void
    {
        DynamicScope::getInstance()->clear();
    }

    protected function tearDown(): void
    {
        DynamicScope::getInstance()->clear();
    }

    public function test_no_binding_when_empty(): void
    {
        $scope = DynamicScope::getInstance();

        self::assertFalse($scope->hasBinding('ns', 'x'));
        self::assertNull($scope->getBinding('ns', 'x'));
        self::assertFalse($scope->hasAnyBinding(), 'fresh scope reports no active frames');
    }

    public function test_any_active_latch_is_false_before_any_push(): void
    {
        // With no dynamic binding ever established, the latch stays false so
        // the hot global-read path skips getInstance() + Fiber::getCurrent().
        self::assertFalse(DynamicScope::$anyActive);

        $scope = DynamicScope::getInstance();
        // A read still resolves correctly while the latch is false.
        self::assertFalse($scope->hasBinding('ns', 'x'));
    }

    public function test_any_active_latch_sets_on_push_and_stays_set_after_pop(): void
    {
        $scope = DynamicScope::getInstance();
        self::assertFalse(DynamicScope::$anyActive);

        $scope->pushFrame(['ns/x' => 1]);
        self::assertTrue(DynamicScope::$anyActive, 'latch flips on first frame push');

        // Conservative one-way latch: popping all frames does NOT reset it,
        // so a binding live in any fiber can never be missed.
        $scope->popFrame();
        self::assertTrue(DynamicScope::$anyActive, 'latch stays set after pop');
    }

    public function test_any_active_latch_resets_only_on_clear(): void
    {
        $scope = DynamicScope::getInstance();
        $scope->pushFrame(['ns/x' => 1]);
        $scope->popFrame();
        self::assertTrue(DynamicScope::$anyActive);

        $scope->clear();
        self::assertFalse(DynamicScope::$anyActive, 'clear() resets the latch');
    }

    public function test_bound_value_is_seen_inside_binding_with_latch_set(): void
    {
        $scope = DynamicScope::getInstance();

        $seen = $scope->withFrame(['ns/x' => 'bound'], static function () use ($scope): mixed {
            self::assertTrue(DynamicScope::$anyActive, 'latch is set inside the binding');
            self::assertTrue($scope->hasAnyBinding());

            return $scope->getBinding('ns', 'x');
        });

        self::assertSame('bound', $seen);
    }

    public function test_has_any_binding_flips_with_push_pop(): void
    {
        $scope = DynamicScope::getInstance();

        self::assertFalse($scope->hasAnyBinding());

        $scope->pushFrame(['ns/x' => 1]);
        self::assertTrue($scope->hasAnyBinding());

        $scope->popFrame();
        self::assertFalse($scope->hasAnyBinding());
    }

    public function test_push_frame_exposes_bindings(): void
    {
        $scope = DynamicScope::getInstance();
        $scope->pushFrame(['ns/x' => 42]);

        self::assertTrue($scope->hasBinding('ns', 'x'));
        self::assertSame(42, $scope->getBinding('ns', 'x'));
    }

    public function test_inner_frame_shadows_outer(): void
    {
        $scope = DynamicScope::getInstance();
        $scope->pushFrame(['ns/x' => 'outer']);
        $scope->pushFrame(['ns/x' => 'inner']);

        self::assertSame('inner', $scope->getBinding('ns', 'x'));

        $scope->popFrame();
        self::assertSame('outer', $scope->getBinding('ns', 'x'));

        $scope->popFrame();
        self::assertFalse($scope->hasBinding('ns', 'x'));
    }

    public function test_with_frame_pops_on_return(): void
    {
        $scope = DynamicScope::getInstance();
        $result = $scope->withFrame(['ns/x' => 7], static fn(): int => 2 * 3);

        self::assertSame(6, $result);
        self::assertFalse($scope->hasBinding('ns', 'x'));
    }

    public function test_with_frame_pops_on_exception(): void
    {
        $scope = DynamicScope::getInstance();

        try {
            $scope->withFrame(['ns/x' => 'boom'], static function (): never {
                throw new RuntimeException('fail');
            });
            self::fail('expected exception');
        } catch (RuntimeException) {
            // expected
        }

        self::assertFalse($scope->hasBinding('ns', 'x'));
    }

    public function test_snapshot_flattens_stack_with_inner_wins(): void
    {
        $scope = DynamicScope::getInstance();
        $scope->pushFrame(['ns/a' => 1, 'ns/b' => 1]);
        $scope->pushFrame(['ns/b' => 2]);

        self::assertSame(['ns/a' => 1, 'ns/b' => 2], $scope->snapshot());
    }

    public function test_fiber_binding_does_not_leak_to_main(): void
    {
        $scope = DynamicScope::getInstance();
        $scope->pushFrame(['ns/x' => 'main']);

        $fiber = new Fiber(static function () use ($scope): void {
            $scope->pushFrame(['ns/x' => 'fiber']);
            Fiber::suspend();
            $scope->popFrame();
        });

        $fiber->start();
        self::assertSame('main', $scope->getBinding('ns', 'x'), 'fiber must not mutate main scope');
        $fiber->resume();
        self::assertTrue($fiber->isTerminated());
        self::assertSame('main', $scope->getBinding('ns', 'x'));

        $scope->popFrame();
    }

    public function test_two_fibers_have_independent_stacks(): void
    {
        $scope = DynamicScope::getInstance();
        $observed = [];

        $fiberA = new Fiber(static function () use ($scope, &$observed): void {
            $scope->pushFrame(['ns/x' => 'A']);
            Fiber::suspend();
            $observed['A'] = $scope->getBinding('ns', 'x');
            $scope->popFrame();
        });

        $fiberB = new Fiber(static function () use ($scope, &$observed): void {
            $scope->pushFrame(['ns/x' => 'B']);
            Fiber::suspend();
            $observed['B'] = $scope->getBinding('ns', 'x');
            $scope->popFrame();
        });

        $fiberA->start();
        $fiberB->start();
        $fiberA->resume();
        $fiberB->resume();

        self::assertSame(['A' => 'A', 'B' => 'B'], $observed);
    }

    public function test_recording_stack_captures_dynamic_and_redefs(): void
    {
        $scope = DynamicScope::getInstance();
        self::assertFalse($scope->isRecording());

        $scope->startRecording();
        self::assertTrue($scope->isRecording());
        $scope->recordDynamic('ns', 'a', 1);
        $scope->recordRedef('ns', 'b', 'old-b');

        $entry = $scope->popRecording();
        self::assertSame(['ns/a' => 1], $entry['dynamic']);
        self::assertSame([['ns', 'b', 'old-b']], $entry['redefs']);
        self::assertFalse($scope->isRecording());
    }

    public function test_pop_recording_on_empty_stack_returns_empty_entry(): void
    {
        $scope = DynamicScope::getInstance();

        $entry = $scope->popRecording();

        self::assertSame(
            ['mode' => DynamicScope::MODE_BINDING, 'dynamic' => [], 'redefs' => []],
            $entry,
        );
    }

    public function test_recording_mode_defaults_to_binding(): void
    {
        $scope = DynamicScope::getInstance();
        $scope->startRecording();

        self::assertSame(DynamicScope::MODE_BINDING, $scope->currentRecordingMode());

        $scope->popRecording();
        self::assertNull($scope->currentRecordingMode());
    }

    public function test_redefs_recording_mode_is_distinguishable(): void
    {
        $scope = DynamicScope::getInstance();
        $scope->startRecording(DynamicScope::MODE_REDEFS);

        self::assertSame(DynamicScope::MODE_REDEFS, $scope->currentRecordingMode());

        $entry = $scope->popRecording();
        self::assertSame(DynamicScope::MODE_REDEFS, $entry['mode']);
    }

    public function test_set_binding_updates_topmost_frame(): void
    {
        $scope = DynamicScope::getInstance();
        $scope->pushFrame(['ns/x' => 'outer']);
        $scope->pushFrame(['ns/x' => 'inner']);

        self::assertTrue($scope->setBinding('ns', 'x', 'inner-edited'));
        self::assertSame('inner-edited', $scope->getBinding('ns', 'x'));

        $scope->popFrame();
        self::assertSame('outer', $scope->getBinding('ns', 'x'), 'lower frame remains untouched');

        $scope->popFrame();
    }

    public function test_set_binding_returns_false_when_no_frame_holds_key(): void
    {
        $scope = DynamicScope::getInstance();

        self::assertFalse($scope->setBinding('ns', 'unbound', 1));

        $scope->pushFrame(['ns/other' => 1]);
        self::assertFalse($scope->setBinding('ns', 'unbound', 1));
        $scope->popFrame();
    }

    public function test_recording_stacks_are_isolated_per_fiber(): void
    {
        $scope = DynamicScope::getInstance();
        $scope->startRecording();
        $scope->recordDynamic('ns', 'x', 'main');

        $observed = null;
        $fiber = new Fiber(static function () use ($scope, &$observed): void {
            self::assertFalse($scope->isRecording(), 'fiber starts without a recording');
            $scope->startRecording();
            $scope->recordDynamic('ns', 'x', 'fiber');

            $observed = $scope->popRecording()['dynamic'];
        });
        $fiber->start();

        self::assertSame(['ns/x' => 'fiber'], $observed);
        self::assertTrue($scope->isRecording(), 'main recording is untouched by fiber');
        self::assertSame(['ns/x' => 'main'], $scope->popRecording()['dynamic']);
    }

    public function test_snapshot_from_fiber_includes_caller_frames_via_apply(): void
    {
        // Simulating binding conveyance: caller snapshots, fiber re-applies.
        $scope = DynamicScope::getInstance();
        $scope->pushFrame(['ns/x' => 'caller']);

        $snapshot = $scope->snapshot();

        $seenInsideFiber = null;
        $fiber = new Fiber(static function () use ($scope, $snapshot, &$seenInsideFiber): void {
            $scope->withFrame($snapshot, static function () use ($scope, &$seenInsideFiber): void {
                $seenInsideFiber = $scope->getBinding('ns', 'x');
            });
        });
        $fiber->start();

        self::assertSame('caller', $seenInsideFiber);
        self::assertSame('caller', $scope->getBinding('ns', 'x'));

        $scope->popFrame();
    }
}
