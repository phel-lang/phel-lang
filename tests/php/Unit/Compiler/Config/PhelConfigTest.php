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
            PhelConfig::SRC_DIRS => ['src/phel'],
            PhelConfig::TEST_DIRS => ['tests/phel'],
            PhelConfig::VENDOR_DIR => 'vendor',
            PhelConfig::ERROR_LOG_FILE => '/tmp/phel-error.log',
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
            PhelConfig::IGNORE_WHEN_BUILDING => [],
            PhelConfig::NO_CACHE_WHEN_BUILDING => [],
            PhelConfig::KEEP_GENERATED_TEMP_FILES => false,
            PhelConfig::TEMP_DIR => sys_get_temp_dir() . '/phel/tmp',
            PhelConfig::FORMAT_DIRS => ['src/phel', 'tests/phel'],
            PhelConfig::ASSERTS_ENABLED => true,
            PhelConfig::ENABLE_NAMESPACE_CACHE => true,
            PhelConfig::ENABLE_COMPILED_CODE_CACHE => true,
            PhelConfig::CACHE_DIR => sys_get_temp_dir() . '/phel/cache',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_for_project_factory(): void
    {
        $config = PhelConfig::forProject('my-app\\core');

        $serialized = $config->jsonSerialize();

        self::assertSame('my-app\\core', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHEL_NAMESPACE]);
        self::assertSame('out/index.php', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHP_PATH]);
        self::assertSame(['src/phel'], $serialized[PhelConfig::SRC_DIRS]);
        self::assertSame(['tests/phel'], $serialized[PhelConfig::TEST_DIRS]);
    }

    public function test_use_flat_layout(): void
    {
        $config = (new PhelConfig())->useFlatLayout();

        $serialized = $config->jsonSerialize();

        self::assertSame(['src'], $serialized[PhelConfig::SRC_DIRS]);
        self::assertSame(['tests'], $serialized[PhelConfig::TEST_DIRS]);
        self::assertSame(['src', 'tests'], $serialized[PhelConfig::FORMAT_DIRS]);
        self::assertSame(['src'], $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::FROM_DIRECTORIES]);
    }

    public function test_use_conventional_layout(): void
    {
        $config = (new PhelConfig())->useFlatLayout()->useConventionalLayout();

        $serialized = $config->jsonSerialize();

        self::assertSame(['src/phel'], $serialized[PhelConfig::SRC_DIRS]);
        self::assertSame(['tests/phel'], $serialized[PhelConfig::TEST_DIRS]);
        self::assertSame(['src/phel', 'tests/phel'], $serialized[PhelConfig::FORMAT_DIRS]);
        self::assertSame(['src/phel'], $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::FROM_DIRECTORIES]);
    }

    public function test_direct_setters_for_build_config(): void
    {
        $config = (new PhelConfig())
            ->setMainPhelNamespace('my-app\\main')
            ->setMainPhpPath('build/app.php')
            ->setBuildDestDir('build');

        $serialized = $config->jsonSerialize();

        self::assertSame('my-app\\main', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHEL_NAMESPACE]);
        self::assertSame('build/app.php', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHP_PATH]);
        self::assertSame('build', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::DEST_DIR]);
    }

    public function test_direct_setters_for_export_config(): void
    {
        $config = (new PhelConfig())
            ->setExportNamespacePrefix('MyGenerated')
            ->setExportTargetDirectory('generated')
            ->setExportFromDirectories(['lib/phel']);

        $serialized = $config->jsonSerialize();

        self::assertSame('MyGenerated', $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::NAMESPACE_PREFIX]);
        self::assertSame('generated', $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::TARGET_DIRECTORY]);
        self::assertSame(['lib/phel'], $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::FROM_DIRECTORIES]);
    }

    public function test_custom_json_serialize(): void
    {
        $config = (new PhelConfig())
            ->setSrcDirs(['some/directory'])
            ->setTestDirs(['another/directory'])
            ->setVendorDir('vendor')
            ->setErrorLogFile('error-log.file')
            ->setBuildConfig((new PhelBuildConfig())
                ->setMainPhpPath('out/custom-index.php')
                ->setMainPhelNamespace('test-ns/boot'))
            ->setExportConfig((new PhelExportConfig())
                ->setFromDirectories(['some/other/dir'])
                ->setNamespacePrefix('Generated')
                ->setTargetDirectory('src/Generated'))
            ->setIgnoreWhenBuilding(['src/ignore.me'])
            ->setNoCacheWhenBuilding(['should-not-be-cached'])
            ->setKeepGeneratedTempFiles(true)
            ->setTempDir('/tmp/custom')
            ->setFormatDirs(['src', 'tests', 'phel'])
            ->setEnableAsserts(false)
            ->setCacheDir('.cache');

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
            PhelConfig::TEMP_DIR => '/tmp/custom',
            PhelConfig::FORMAT_DIRS => ['src', 'tests', 'phel'],
            PhelConfig::ASSERTS_ENABLED => false,
            PhelConfig::ENABLE_NAMESPACE_CACHE => true,
            PhelConfig::ENABLE_COMPILED_CODE_CACHE => true,
            PhelConfig::CACHE_DIR => '.cache',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_getters(): void
    {
        $config = new PhelConfig();

        self::assertSame(['src/phel'], $config->getSrcDirs());
        self::assertSame(['tests/phel'], $config->getTestDirs());
        self::assertSame('vendor', $config->getVendorDir());
        self::assertSame('/tmp/phel-error.log', $config->getErrorLogFile());
        self::assertInstanceOf(PhelBuildConfig::class, $config->getBuildConfig());
        self::assertInstanceOf(PhelExportConfig::class, $config->getExportConfig());
        self::assertSame([], $config->getIgnoreWhenBuilding());
        self::assertSame([], $config->getNoCacheWhenBuilding());
        self::assertFalse($config->getKeepGeneratedTempFiles());
        self::assertSame(['src/phel', 'tests/phel'], $config->getFormatDirs());
        self::assertTrue($config->isAssertsEnabled());
        self::assertTrue($config->isNamespaceCacheEnabled());
        self::assertTrue($config->isCompiledCodeCacheEnabled());
    }

    public function test_validate_passes_for_relative_paths(): void
    {
        $config = new PhelConfig();
        $errors = $config->validate();

        self::assertSame([], $errors);
    }

    public function test_validate_fails_for_absolute_src_dir(): void
    {
        $config = new PhelConfig();
        $config->setSrcDirs(['/absolute/path']);

        $errors = $config->validate();

        self::assertCount(1, $errors);
        self::assertStringContainsString('should be relative', $errors[0]);
    }

    public function test_validate_fails_for_absolute_test_dir(): void
    {
        $config = new PhelConfig();
        $config->setTestDirs(['/absolute/tests']);

        $errors = $config->validate();

        self::assertCount(1, $errors);
        self::assertStringContainsString('Test directory', $errors[0]);
    }

    public function test_validate_fails_for_absolute_vendor_dir(): void
    {
        $config = new PhelConfig();
        $config->setVendorDir('/absolute/vendor');

        $errors = $config->validate();

        self::assertCount(1, $errors);
        self::assertStringContainsString('Vendor directory', $errors[0]);
    }

    public function test_temp_dir_uses_single_base_path(): void
    {
        $config = new PhelConfig();

        $tempDir = $config->getTempDir();
        $cacheDir = $config->getCacheDir();

        self::assertStringContainsString('/phel/', $tempDir);
        self::assertStringContainsString('/phel/', $cacheDir);
        self::assertStringEndsWith('/tmp', $tempDir);
        self::assertStringEndsWith('/cache', $cacheDir);
    }
}
