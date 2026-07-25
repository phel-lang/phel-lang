<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Performance;

use Phel\Shared\Performance\OpcacheReexecDecision;
use PHPUnit\Framework\TestCase;

final class OpcacheReexecDecisionTest extends TestCase
{
    public function test_user_ini_flags_are_placed_before_the_forced_ones(): void
    {
        // PHP applies `-d` left to right with the last occurrence winning, so
        // "user first" is what lets Phel's three OPcache directives stay
        // authoritative while every other override survives.
        $decision = new OpcacheReexecDecision(true, ['-d', 'opcache.enable_cli=1'])
            ->withUserIniFlags(['-d', 'display_errors=stderr']);

        self::assertTrue($decision->shouldReexec);
        self::assertSame(
            ['-d', 'display_errors=stderr', '-d', 'opcache.enable_cli=1'],
            $decision->flags,
        );
    }

    public function test_forced_opcache_flags_override_a_conflicting_user_flag(): void
    {
        $decision = new OpcacheReexecDecision(true, ['-d', 'opcache.enable_cli=1'])
            ->withUserIniFlags(['-d', 'opcache.enable_cli=0']);

        self::assertSame(
            ['-d', 'opcache.enable_cli=0', '-d', 'opcache.enable_cli=1'],
            $decision->flags,
        );
    }

    public function test_returns_the_same_decision_when_there_is_nothing_to_carry_over(): void
    {
        $decision = new OpcacheReexecDecision(true, ['-d', 'opcache.enable_cli=1']);

        self::assertSame($decision, $decision->withUserIniFlags([]));
    }
}
