<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Config;

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;
use PHPUnit\Framework\TestCase;

final class PhelConfigTest extends TestCase
{
    public function test_default_json_serialize(): void
    {
        $config = new PhelConfig();

        $expected = [
            PhelConfig::SRC_DIRS => ['src'],
            PhelConfig::TEST_DIRS => ['tests'],
            PhelConfig::VENDOR_DIR => 'vendor',
            PhelConfig::ERROR_LOG_FILE => 'data/error.log',
            PhelConfig::BUILD_CONFIG => [
                PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
                PhelBuildConfig::DEST_DIR => 'out',
                PhelBuildConfig::MAIN_PHP_FILENAME => 'index.php',
                PhelBuildConfig::MAIN_PHP_PATH => 'out/index.php',
            ],
            PhelConfig::EXPORT_CONFIG => [
                PhelExportConfig::TARGET_DIRECTORY => 'src/PhelGenerated',
                PhelExportConfig::FROM_DIRECTORIES => ['src/phel'],
                PhelExportConfig::NAMESPACE_PREFIX => 'PhelGenerated',
            ],
            PhelConfig::IGNORE_WHEN_BUILDING => ['src/phel/local.phel'],
            PhelConfig::NO_CACHE_WHEN_BUILDING => [],
            PhelConfig::KEEP_GENERATED_TEMP_FILES => false,
            PhelConfig::FORMAT_DIRS => ['src', 'tests'],
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_custom_json_serialize(): void
    {
        $config = (new PhelConfig())
            ->setSrcDirs(['some/directory'])
            ->setTestDirs(['another/directory'])
            ->setVendorDir('vendor')
            ->setErrorLogFile('error-log.file')
            ->setBuildConfig(
                (new PhelBuildConfig())
                    ->setMainPhpPath('out/custom-index.php')
                    ->setMainPhelNamespace('test-ns/boot'),
            )
            ->setExportConfig(
                (new PhelExportConfig())
                    ->setFromDirectories(['some/other/dir'])
                    ->setNamespacePrefix('Generated')
                    ->setTargetDirectory('src/Generated'),
            )
            ->setIgnoreWhenBuilding(['src/ignore.me'])
            ->setNoCacheWhenBuilding(['should-not-be-cached'])
            ->setKeepGeneratedTempFiles(true)
            ->setFormatDirs(['src', 'tests', 'phel']);

        $expected = [
            PhelConfig::SRC_DIRS => ['some/directory'],
            PhelConfig::TEST_DIRS => ['another/directory'],
            PhelConfig::VENDOR_DIR => 'vendor',
            PhelConfig::ERROR_LOG_FILE => 'error-log.file',
            PhelConfig::BUILD_CONFIG => [
                PhelBuildConfig::MAIN_PHEL_NAMESPACE => 'test-ns/boot',
                PhelBuildConfig::DEST_DIR => 'out',
                PhelBuildConfig::MAIN_PHP_FILENAME => 'custom-index.php',
                PhelBuildConfig::MAIN_PHP_PATH => 'out/custom-index.php',
            ],
            PhelConfig::EXPORT_CONFIG => [
                PhelExportConfig::TARGET_DIRECTORY => 'src/Generated',
                PhelExportConfig::FROM_DIRECTORIES => ['some/other/dir'],
                PhelExportConfig::NAMESPACE_PREFIX => 'Generated',
            ],
            PhelConfig::IGNORE_WHEN_BUILDING => ['src/ignore.me'],
            PhelConfig::NO_CACHE_WHEN_BUILDING => ['should-not-be-cached'],
            PhelConfig::KEEP_GENERATED_TEMP_FILES => true,
            PhelConfig::FORMAT_DIRS => ['src', 'tests', 'phel'],
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }
}
