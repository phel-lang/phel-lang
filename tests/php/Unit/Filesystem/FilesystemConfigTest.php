<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Config\PhelConfig;
use Phel\Filesystem\FilesystemConfig;
use PHPUnit\Framework\TestCase;

final class FilesystemConfigTest extends TestCase
{
    public function test_a_config_without_a_temp_dir_gets_the_per_user_default(): void
    {
        // An array `phel-config.php` has no `temp-dir` key. Falling back to the
        // bare system temp dir puts generated PHP in `/tmp`, which belongs to
        // root on Linux, so every compile fails the ownership check.
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {});

        self::assertSame(PhelConfig::defaultTempDir(), new FilesystemConfig()->getTempDir());
    }

    public function test_a_configured_temp_dir_wins(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::TEMP_DIR, '/var/phel/tmp');
        });

        self::assertSame('/var/phel/tmp', new FilesystemConfig()->getTempDir());
    }
}
