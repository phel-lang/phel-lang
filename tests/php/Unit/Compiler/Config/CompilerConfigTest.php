<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerConfig;
use Phel\Config\PhelConfig;
use PHPUnit\Framework\TestCase;

final class CompilerConfigTest extends TestCase
{
    public function test_intermediate_cache_disabled_by_default(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {});

        self::assertFalse(new CompilerConfig()->isIntermediateCacheEnabled());
    }

    public function test_intermediate_cache_enabled_when_configured(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::ENABLE_INTERMEDIATE_CACHE, true);
        });

        self::assertTrue(new CompilerConfig()->isIntermediateCacheEnabled());
    }

    public function test_absolute_cache_dir_returned_as_is(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::CACHE_DIR, '/var/cache/phel');
        });

        self::assertSame('/var/cache/phel', new CompilerConfig()->getCacheDir());
    }

    public function test_phel_cache_dir_env_overrides_configured_value(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::CACHE_DIR, '/configured/cache');
        });
        putenv('PHEL_CACHE_DIR=/env/override/cache');

        try {
            self::assertSame('/env/override/cache', new CompilerConfig()->getCacheDir());
        } finally {
            putenv('PHEL_CACHE_DIR');
        }
    }
}
