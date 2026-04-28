<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Build\BuildConfig;
use Phel\Config\PhelConfig;
use PHPUnit\Framework\TestCase;

final class BuildConfigTest extends TestCase
{
    public function test_posix_absolute_cache_dir_returned_as_is(): void
    {
        $this->bootstrapWithCacheDir('/var/cache/phel');

        $config = new BuildConfig();

        self::assertSame('/var/cache/phel', $config->getCacheDir());
    }

    public function test_phar_absolute_cache_dir_returned_as_is(): void
    {
        $this->bootstrapWithCacheDir('phar:///app.phar/cache');

        $config = new BuildConfig();

        self::assertSame('phar:///app.phar/cache', $config->getCacheDir());
    }

    public function test_windows_drive_letter_cache_dir_returned_as_is(): void
    {
        $this->bootstrapWithCacheDir('C:\\Users\\user\\AppData\\Local\\Temp\\phel\\cache');

        $config = new BuildConfig();

        self::assertSame(
            'C:\\Users\\user\\AppData\\Local\\Temp\\phel\\cache',
            $config->getCacheDir(),
        );
    }

    public function test_windows_drive_letter_with_forward_slashes_cache_dir_returned_as_is(): void
    {
        $this->bootstrapWithCacheDir('C:/Users/user/AppData/Local/Temp/phel/cache');

        $config = new BuildConfig();

        self::assertSame(
            'C:/Users/user/AppData/Local/Temp/phel/cache',
            $config->getCacheDir(),
        );
    }

    public function test_windows_unc_cache_dir_returned_as_is(): void
    {
        $this->bootstrapWithCacheDir('\\\\server\\share\\phel\\cache');

        $config = new BuildConfig();

        self::assertSame('\\\\server\\share\\phel\\cache', $config->getCacheDir());
    }

    public function test_relative_cache_dir_prefixed_with_app_root(): void
    {
        $this->bootstrapWithCacheDir('cache');

        $config = new BuildConfig();

        self::assertStringEndsWith('/cache', $config->getCacheDir());
        self::assertNotSame('cache', $config->getCacheDir());
    }

    private function bootstrapWithCacheDir(string $cacheDir): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->addAppConfigKeyValue(PhelConfig::CACHE_DIR, $cacheDir);
        });
    }
}
