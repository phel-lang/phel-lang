<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Config;

use Phel\Config\PhelOutConfig;
use PHPUnit\Framework\TestCase;

final class PhelOutConfigTest extends TestCase
{
    public function test_default(): void
    {
        $config = (new PhelOutConfig());

        $expected = [
            PhelOutConfig::MAIN_PHEL_NAMESPACE => '',
            PhelOutConfig::DEST_DIR => 'out',
            PhelOutConfig::MAIN_PHP_FILENAME => 'index.php',
            PhelOutConfig::MAIN_PHP_PATH => 'out/index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_defined_phel_ns(): void
    {
        $config = (new PhelOutConfig())
            ->setMainPhelNamespace('test-ns/boot');

        $expected = [
            PhelOutConfig::MAIN_PHEL_NAMESPACE => 'test-ns/boot',
            PhelOutConfig::DEST_DIR => 'out',
            PhelOutConfig::MAIN_PHP_FILENAME => 'index.php',
            PhelOutConfig::MAIN_PHP_PATH => 'out/index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_dest_dir(): void
    {
        $config = (new PhelOutConfig())
            ->setDestDir('custom-out');

        $expected = [
            PhelOutConfig::MAIN_PHEL_NAMESPACE => '',
            PhelOutConfig::DEST_DIR => 'custom-out',
            PhelOutConfig::MAIN_PHP_FILENAME => 'index.php',
            PhelOutConfig::MAIN_PHP_PATH => 'custom-out/index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_dest_dir_and_main_php_filename(): void
    {
        $config = (new PhelOutConfig())
            ->setDestDir('custom-out')
            ->setMainPhpFilename('custom-index');

        $expected = [
            PhelOutConfig::MAIN_PHEL_NAMESPACE => '',
            PhelOutConfig::DEST_DIR => 'custom-out',
            PhelOutConfig::MAIN_PHP_FILENAME => 'custom-index.php',
            PhelOutConfig::MAIN_PHP_PATH => 'custom-out/custom-index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_main_php_path_over_php_filename(): void
    {
        $config = (new PhelOutConfig())
            ->setMainPhpPath('custom-out/custom-index.php')
            ->setMainPhpFilename('other-name');

        $expected = [
            PhelOutConfig::MAIN_PHEL_NAMESPACE => '',
            PhelOutConfig::DEST_DIR => 'custom-out',
            PhelOutConfig::MAIN_PHP_FILENAME => 'custom-index.php',
            PhelOutConfig::MAIN_PHP_PATH => 'custom-out/custom-index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_main_php_path(): void
    {
        $config = (new PhelOutConfig())
            ->setMainPhpPath('custom-out/custom-index.php');

        $expected = [
            PhelOutConfig::MAIN_PHEL_NAMESPACE => '',
            PhelOutConfig::DEST_DIR => 'custom-out',
            PhelOutConfig::MAIN_PHP_FILENAME => 'custom-index.php',
            PhelOutConfig::MAIN_PHP_PATH => 'custom-out/custom-index.php',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }
}
