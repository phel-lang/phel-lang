<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Config\PhelConfig;
use Phel\Run\RunConfig;
use PHPUnit\Framework\TestCase;

final class RunConfigTest extends TestCase
{
    public function test_optimization_level_defaults_to_zero(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {});

        self::assertSame(0, new RunConfig()->getOptimizationLevel());
    }

    public function test_optimization_level_read_from_config(): void
    {
        $this->bootstrapWithOptimizationLevel(2);

        self::assertSame(2, new RunConfig()->getOptimizationLevel());
    }

    public function test_negative_optimization_level_clamped_to_zero(): void
    {
        $this->bootstrapWithOptimizationLevel(-1);

        self::assertSame(0, new RunConfig()->getOptimizationLevel());
    }

    private function bootstrapWithOptimizationLevel(int $level): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($level): void {
            $config->addAppConfigKeyValue(PhelConfig::OPTIMIZATION_LEVEL, $level);
        });
    }
}
