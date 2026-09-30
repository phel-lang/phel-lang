<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\OptimizationLevel;
use PHPUnit\Framework\TestCase;

use function putenv;

final class OptimizationLevelTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(OptimizationLevel::PIN_ENV);
    }

    public function test_the_configured_level_applies_without_a_pin(): void
    {
        self::assertSame(2, OptimizationLevel::resolve(2));
        self::assertSame(0, OptimizationLevel::resolve(-1));
        self::assertSame(0, OptimizationLevel::resolve('not a level'));
    }

    public function test_a_pin_wins_over_the_configured_level(): void
    {
        OptimizationLevel::pin(0);

        self::assertSame(0, OptimizationLevel::resolve(2));
    }
}
