<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Config;

use Phel\Config\PhelBuildConfig;
use PHPUnit\Framework\TestCase;

final class PhelBuildConfigTest extends TestCase
{
    public function test_default(): void
    {
        $config = (new PhelBuildConfig());

        $expected = [
            PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
            PhelBuildConfig::DEST_DIR => 'out',
            PhelBuildConfig::MAIN_PHP_FILENAME => 'index.php',
            PhelBuildConfig::MAIN_PHP_PATH => 'out/index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_defined_phel_ns(): void
    {
        $config = (new PhelBuildConfig())
            ->setMainPhelNamespace('test-ns/boot');

        $expected = [
            PhelBuildConfig::MAIN_PHEL_NAMESPACE => 'test-ns/boot',
            PhelBuildConfig::DEST_DIR => 'out',
            PhelBuildConfig::MAIN_PHP_FILENAME => 'index.php',
            PhelBuildConfig::MAIN_PHP_PATH => 'out/index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_dest_dir(): void
    {
        $config = (new PhelBuildConfig())
            ->setDestDir('custom-out');

        $expected = [
            PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
            PhelBuildConfig::DEST_DIR => 'custom-out',
            PhelBuildConfig::MAIN_PHP_FILENAME => 'index.php',
            PhelBuildConfig::MAIN_PHP_PATH => 'custom-out/index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_dest_dir_and_main_php_filename(): void
    {
        $config = (new PhelBuildConfig())
            ->setDestDir('custom-out')
            ->setMainPhpFilename('custom-index');

        $expected = [
            PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
            PhelBuildConfig::DEST_DIR => 'custom-out',
            PhelBuildConfig::MAIN_PHP_FILENAME => 'custom-index.php',
            PhelBuildConfig::MAIN_PHP_PATH => 'custom-out/custom-index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_main_php_path_over_php_filename(): void
    {
        $config = (new PhelBuildConfig())
            ->setMainPhpPath('custom-out/custom-index.php')
            ->setMainPhpFilename('other-name');

        $expected = [
            PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
            PhelBuildConfig::DEST_DIR => 'custom-out',
            PhelBuildConfig::MAIN_PHP_FILENAME => 'custom-index.php',
            PhelBuildConfig::MAIN_PHP_PATH => 'custom-out/custom-index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_main_php_path(): void
    {
        $config = (new PhelBuildConfig())
            ->setMainPhpPath('custom-out/custom-index.php');

        $expected = [
            PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
            PhelBuildConfig::DEST_DIR => 'custom-out',
            PhelBuildConfig::MAIN_PHP_FILENAME => 'custom-index.php',
            PhelBuildConfig::MAIN_PHP_PATH => 'custom-out/custom-index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_main_php_path_without_ext(): void
    {
        $config = (new PhelBuildConfig())
            ->setMainPhpPath('custom-flip');

        $expected = [
            PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
            PhelBuildConfig::DEST_DIR => 'out',
            PhelBuildConfig::MAIN_PHP_FILENAME => 'custom-flip.php',
            PhelBuildConfig::MAIN_PHP_PATH => 'out/custom-flip.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_main_php_path_bug_when_not_dir_defined(): void
    {
        $config = (new PhelBuildConfig())
            ->setMainPhpPath('custom-index');

        $expected = [
            PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
            PhelBuildConfig::DEST_DIR => 'out',
            PhelBuildConfig::MAIN_PHP_FILENAME => 'custom-index.php',
            PhelBuildConfig::MAIN_PHP_PATH => 'out/custom-index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_main_php_path_bug_when_nested_dir_defined(): void
    {
        $config = (new PhelBuildConfig())
            ->setMainPhpPath('custom-dir1/dir2/custom-index');

        $expected = [
            PhelBuildConfig::MAIN_PHEL_NAMESPACE => '',
            PhelBuildConfig::DEST_DIR => 'custom-dir1/dir2',
            PhelBuildConfig::MAIN_PHP_FILENAME => 'custom-index.php',
            PhelBuildConfig::MAIN_PHP_PATH => 'custom-dir1/dir2/custom-index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }
}
