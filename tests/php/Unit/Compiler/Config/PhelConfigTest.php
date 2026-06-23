<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Config;

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;
use Phel\Config\ProjectLayout;
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
            PhelConfig::ERROR_LOG_FILE => '.phel/error.log',
            PhelConfig::BUILD_CONFIG => [
                PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
                PhelBuildConfig::DEST_DIR => 'out',
                PhelBuildConfig::MAIN_PHP_FILENAME => 'index.php',
                PhelBuildConfig::MAIN_PHP_PATH => 'out/index.php',
            ],
            PhelConfig::EXPORT_CONFIG => [
                PhelExportConfig::TARGET_DIRECTORY => 'src/PhelGenerated',
                PhelExportConfig::FROM_DIRECTORIES => ['src'],
                PhelExportConfig::NAMESPACE_PREFIX => 'PhelGenerated',
            ],
            PhelConfig::IGNORE_WHEN_BUILDING => [],
            PhelConfig::NO_CACHE_WHEN_BUILDING => [],
            PhelConfig::KEEP_GENERATED_TEMP_FILES => false,
            PhelConfig::TEMP_DIR => sys_get_temp_dir() . '/phel/tmp',
            PhelConfig::FORMAT_DIRS => ['src', 'tests'],
            PhelConfig::ASSERTS_ENABLED => true,
            PhelConfig::WARN_DEPRECATIONS => false,
            PhelConfig::ENABLE_NAMESPACE_CACHE => true,
            PhelConfig::ENABLE_COMPILED_CODE_CACHE => true,
            PhelConfig::CACHE_DIR => '.phel/cache',
            PhelConfig::PHEL_DIR => '',
            PhelConfig::OPTIMIZATION_LEVEL => 0,
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_optimization_level_defaults_to_zero(): void
    {
        $config = new PhelConfig();

        self::assertSame(0, $config->getOptimizationLevel());
    }

    public function test_with_optimization_level(): void
    {
        $config = new PhelConfig();
        $updated = $config->withOptimizationLevel(2);

        self::assertNotSame($config, $updated);
        self::assertSame(0, $config->getOptimizationLevel());
        self::assertSame(2, $updated->getOptimizationLevel());
        self::assertSame(2, $updated->jsonSerialize()[PhelConfig::OPTIMIZATION_LEVEL]);
    }

    public function test_with_optimization_level_clamps_negative_values(): void
    {
        $config = new PhelConfig()->withOptimizationLevel(-1);

        self::assertSame(0, $config->getOptimizationLevel());
    }

    public function test_for_project_factory(): void
    {
        $config = PhelConfig::forProject(ProjectLayout::Nested)
            ->withMainPhelNamespace('my-app\\core');

        $serialized = $config->jsonSerialize();

        self::assertSame('my-app\\core', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHEL_NAMESPACE]);
        self::assertSame('out/index.php', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHP_PATH]);
        self::assertSame(['src/phel'], $serialized[PhelConfig::SRC_DIRS]);
        self::assertSame(['tests/phel'], $serialized[PhelConfig::TEST_DIRS]);
    }

    public function test_for_project_factory_with_flat_layout(): void
    {
        $config = PhelConfig::forProject(ProjectLayout::Flat)
            ->withMainPhelNamespace('my-app\\main');

        $serialized = $config->jsonSerialize();

        self::assertSame('my-app\\main', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHEL_NAMESPACE]);
        self::assertSame(['src'], $serialized[PhelConfig::SRC_DIRS]);
        self::assertSame(['tests'], $serialized[PhelConfig::TEST_DIRS]);
        self::assertSame(['src', 'tests'], $serialized[PhelConfig::FORMAT_DIRS]);
        self::assertSame(['src'], $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::FROM_DIRECTORIES]);
    }

    public function test_for_project_factory_with_root_layout(): void
    {
        $config = PhelConfig::forProject(ProjectLayout::Root)
            ->withMainPhelNamespace('sandbox\\main');

        $serialized = $config->jsonSerialize();

        self::assertSame('sandbox\\main', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHEL_NAMESPACE]);
        self::assertSame(['.'], $serialized[PhelConfig::SRC_DIRS]);
        self::assertSame(['.'], $serialized[PhelConfig::TEST_DIRS]);
        self::assertSame(['.'], $serialized[PhelConfig::FORMAT_DIRS]);
        self::assertSame(['.'], $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::FROM_DIRECTORIES]);
    }

    public function test_for_project_factory_without_namespace(): void
    {
        $config = PhelConfig::forProject(ProjectLayout::Nested);

        $serialized = $config->jsonSerialize();

        self::assertSame('', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHEL_NAMESPACE]);
        self::assertSame(['src/phel'], $serialized[PhelConfig::SRC_DIRS]);
    }

    public function test_for_project_factory_defaults_to_flat_layout(): void
    {
        $config = PhelConfig::forProject();

        $serialized = $config->jsonSerialize();

        self::assertSame(['src'], $serialized[PhelConfig::SRC_DIRS]);
        self::assertSame(['tests'], $serialized[PhelConfig::TEST_DIRS]);
    }

    public function test_with_layout_flat(): void
    {
        $config = new PhelConfig()->withLayout(ProjectLayout::Flat);

        $serialized = $config->jsonSerialize();

        self::assertSame(['src'], $serialized[PhelConfig::SRC_DIRS]);
        self::assertSame(['tests'], $serialized[PhelConfig::TEST_DIRS]);
        self::assertSame(['src', 'tests'], $serialized[PhelConfig::FORMAT_DIRS]);
        self::assertSame(['src'], $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::FROM_DIRECTORIES]);
    }

    public function test_with_layout_nested(): void
    {
        $config = new PhelConfig()->withLayout(ProjectLayout::Nested);

        $serialized = $config->jsonSerialize();

        self::assertSame(['src/phel'], $serialized[PhelConfig::SRC_DIRS]);
        self::assertSame(['tests/phel'], $serialized[PhelConfig::TEST_DIRS]);
        self::assertSame(['src/phel', 'tests/phel'], $serialized[PhelConfig::FORMAT_DIRS]);
        self::assertSame(['src/phel'], $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::FROM_DIRECTORIES]);
    }

    public function test_with_main_phel_namespace_sets_default_php_path(): void
    {
        $config = new PhelConfig()
            ->withMainPhelNamespace('my-app\\main')
            ->withMainPhpPath('build/app.php')
            ->withBuildDestDir('build');

        $serialized = $config->jsonSerialize();

        self::assertSame('my-app\\main', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHEL_NAMESPACE]);
        self::assertSame('build/app.php', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHP_PATH]);
        self::assertSame('build', $serialized[PhelConfig::BUILD_CONFIG][PhelBuildConfig::DEST_DIR]);
    }

    public function test_main_php_path_follows_dest_dir_regardless_of_wither_order(): void
    {
        $nsFirst = new PhelConfig()
            ->withMainPhelNamespace('app\\main')
            ->withBuildDestDir('dist')
            ->jsonSerialize();

        $destFirst = new PhelConfig()
            ->withBuildDestDir('dist')
            ->withMainPhelNamespace('app\\main')
            ->jsonSerialize();

        self::assertSame('dist/index.php', $nsFirst[PhelConfig::BUILD_CONFIG][PhelBuildConfig::MAIN_PHP_PATH]);
        self::assertSame($destFirst, $nsFirst);
    }

    public function test_with_export_proxy_methods(): void
    {
        $config = new PhelConfig()
            ->withExportNamespacePrefix('MyGenerated')
            ->withExportTargetDirectory('generated')
            ->withExportFromDirectories(['lib/phel']);

        $serialized = $config->jsonSerialize();

        self::assertSame('MyGenerated', $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::NAMESPACE_PREFIX]);
        self::assertSame('generated', $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::TARGET_DIRECTORY]);
        self::assertSame(['lib/phel'], $serialized[PhelConfig::EXPORT_CONFIG][PhelExportConfig::FROM_DIRECTORIES]);
    }

    public function test_custom_json_serialize_with_immutable_api(): void
    {
        $config = new PhelConfig()
            ->withSrcDirs(['some/directory'])
            ->withTestDirs(['another/directory'])
            ->withVendorDir('vendor')
            ->withErrorLogFile('error-log.file')
            ->withMainPhelNamespace('test-ns/boot')
            ->withMainPhpPath('out/custom-index.php')
            ->withExportFromDirectories(['some/other/dir'])
            ->withExportNamespacePrefix('Generated')
            ->withExportTargetDirectory('src/Generated')
            ->withIgnoreWhenBuilding(['src/ignore.me'])
            ->withNoCacheWhenBuilding(['should-not-be-cached'])
            ->withKeepGeneratedTempFiles(true)
            ->withTempDir('/tmp/custom')
            ->withFormatDirs(['src', 'tests', 'phel'])
            ->withEnableAsserts(false)
            ->withWarnDeprecations(true)
            ->withCacheDir('.cache')
            ->withOptimizationLevel(2);

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
            PhelConfig::WARN_DEPRECATIONS => true,
            PhelConfig::ENABLE_NAMESPACE_CACHE => true,
            PhelConfig::ENABLE_COMPILED_CODE_CACHE => true,
            PhelConfig::CACHE_DIR => '.cache',
            PhelConfig::PHEL_DIR => '',
            PhelConfig::OPTIMIZATION_LEVEL => 2,
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_boolean_withers_default_to_true(): void
    {
        $config = new PhelConfig()
            ->withWarnDeprecations()
            ->withKeepGeneratedTempFiles()
            ->withEnableAsserts(false)
            ->withEnableNamespaceCache(false)
            ->withEnableCompiledCodeCache(false);

        self::assertTrue($config->shouldWarnDeprecations());
        self::assertTrue($config->getKeepGeneratedTempFiles());
        self::assertFalse($config->isAssertsEnabled());
        self::assertFalse($config->isNamespaceCacheEnabled());
        self::assertFalse($config->isCompiledCodeCacheEnabled());
    }

    public function test_with_phel_dir_persists_in_json(): void
    {
        $config = new PhelConfig()->withPhelDir('/var/cache/phel');

        self::assertSame('/var/cache/phel', $config->getPhelDir());
        self::assertSame('/var/cache/phel', $config->jsonSerialize()[PhelConfig::PHEL_DIR]);
    }

    public function test_with_build_config_closure_patches_in_place(): void
    {
        $config = new PhelConfig()
            ->withMainPhelNamespace('app\\main')
            ->withBuildConfig(static fn(PhelBuildConfig $b): PhelBuildConfig => $b->withDestDir('dist'));

        $build = $config->jsonSerialize()[PhelConfig::BUILD_CONFIG];
        // The closure form preserves the previously set namespace ...
        self::assertSame('app\\main', $build[PhelBuildConfig::MAIN_PHEL_NAMESPACE]);
        // ... and applies the patch, deriving the entry point under the new dest dir.
        self::assertSame('dist', $build[PhelBuildConfig::DEST_DIR]);
        self::assertSame('dist/index.php', $build[PhelBuildConfig::MAIN_PHP_PATH]);
    }

    public function test_with_export_config_closure_patches_in_place(): void
    {
        $config = new PhelConfig()
            ->withExportTargetDirectory('gen')
            ->withExportConfig(static fn(PhelExportConfig $e): PhelExportConfig => $e->withNamespacePrefix('App'));

        $export = $config->jsonSerialize()[PhelConfig::EXPORT_CONFIG];
        // Closure form keeps the earlier target directory ...
        self::assertSame('gen', $export[PhelExportConfig::TARGET_DIRECTORY]);
        // ... and patches the prefix.
        self::assertSame('App', $export[PhelExportConfig::NAMESPACE_PREFIX]);
    }

    public function test_with_methods_return_new_instance(): void
    {
        $original = new PhelConfig();
        $updated = $original->withVendorDir('custom-vendor');

        self::assertNotSame($original, $updated);
        self::assertSame('vendor', $original->getVendorDir());
        self::assertSame('custom-vendor', $updated->getVendorDir());
    }

    public function test_immutability_of_build_config_via_proxy(): void
    {
        $original = new PhelConfig();
        $updated = $original->withMainPhelNamespace('app\\boot');

        self::assertNotSame($original, $updated);
        self::assertNotSame($original->getBuildConfig(), $updated->getBuildConfig());
        self::assertSame('', $original->getBuildConfig()->getMainPhelNamespace());
        self::assertSame('app\\boot', $updated->getBuildConfig()->getMainPhelNamespace());
    }

    public function test_with_build_config_replaces_whole_value_object(): void
    {
        $build = new PhelBuildConfig(
            mainPhelNamespace: 'lib\\entry',
            mainPhpPath: 'dist/app.php',
        );

        $config = new PhelConfig()->withBuildConfig($build);

        self::assertSame($build, $config->getBuildConfig());
    }

    public function test_with_export_config_replaces_whole_value_object(): void
    {
        $export = new PhelExportConfig(
            fromDirectories: ['lib'],
            namespacePrefix: 'Generated',
            targetDirectory: 'gen',
        );

        $config = new PhelConfig()->withExportConfig($export);

        self::assertSame($export, $config->getExportConfig());
    }

    public function test_getters(): void
    {
        $config = new PhelConfig();

        self::assertSame(['src'], $config->getSrcDirs());
        self::assertSame(['tests'], $config->getTestDirs());
        self::assertSame('vendor', $config->getVendorDir());
        self::assertSame('.phel/error.log', $config->getErrorLogFile());
        self::assertInstanceOf(PhelBuildConfig::class, $config->getBuildConfig());
        self::assertInstanceOf(PhelExportConfig::class, $config->getExportConfig());
        self::assertSame([], $config->getIgnoreWhenBuilding());
        self::assertSame([], $config->getNoCacheWhenBuilding());
        self::assertFalse($config->getKeepGeneratedTempFiles());
        self::assertSame(['src', 'tests'], $config->getFormatDirs());
        self::assertTrue($config->isAssertsEnabled());
        self::assertFalse($config->shouldWarnDeprecations());
        self::assertTrue($config->isNamespaceCacheEnabled());
        self::assertTrue($config->isCompiledCodeCacheEnabled());
    }

    public function test_validate_passes_for_relative_paths(): void
    {
        $errors = new PhelConfig()->validate();

        self::assertSame([], $errors);
    }

    public function test_validate_fails_for_absolute_src_dir(): void
    {
        $errors = new PhelConfig()
            ->withSrcDirs(['/absolute/path'])
            ->validate();

        self::assertCount(1, $errors);
        self::assertStringContainsString('should be relative', $errors[0]);
    }

    public function test_validate_fails_for_absolute_test_dir(): void
    {
        $errors = new PhelConfig()
            ->withTestDirs(['/absolute/tests'])
            ->validate();

        self::assertCount(1, $errors);
        self::assertStringContainsString('Test directory', $errors[0]);
    }

    public function test_validate_fails_for_absolute_vendor_dir(): void
    {
        $errors = new PhelConfig()
            ->withVendorDir('/absolute/vendor')
            ->validate();

        self::assertCount(1, $errors);
        self::assertStringContainsString('Vendor directory', $errors[0]);
    }

    public function test_temp_dir_default_lives_under_system_temp(): void
    {
        $tempDir = new PhelConfig()->getTempDir();

        self::assertStringContainsString('/phel/', $tempDir);
        self::assertStringEndsWith('/tmp', $tempDir);
    }

    public function test_cache_dir_default_is_relative_to_project_root(): void
    {
        self::assertSame('.phel/cache', new PhelConfig()->getCacheDir());
    }

    public function test_cache_dir_trailing_separator_normalized_in_constructor(): void
    {
        $viaConstructor = new PhelConfig(cacheDir: 'custom/cache/');
        $viaWither = new PhelConfig()->withCacheDir('custom/cache/');

        self::assertSame('custom/cache', $viaConstructor->getCacheDir());
        self::assertSame('custom/cache', $viaWither->getCacheDir());
    }
}
