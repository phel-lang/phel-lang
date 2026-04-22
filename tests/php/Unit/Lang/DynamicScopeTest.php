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

        self::assertSame(['dynamic' => [], 'redefs' => []], $entry);
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
