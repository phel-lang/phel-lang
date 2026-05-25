<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

final class CompileOptionsOptimizationLevelTest extends TestCase
{
    public function test_default_optimization_level_is_zero(): void
    {
        self::assertSame(0, new CompileOptions()->getOptimizationLevel());
    }

    public function test_set_level_round_trips(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(2);

        self::assertSame(2, $options->getOptimizationLevel());
    }

    public function test_negative_level_clamps_to_zero(): void
    {
        $options = new CompileOptions()->setOptimizationLevel(-5);

        self::assertSame(0, $options->getOptimizationLevel());
    }

    public function test_set_level_returns_same_instance(): void
    {
        $options = new CompileOptions();

        self::assertSame($options, $options->setOptimizationLevel(1));
    }
}
