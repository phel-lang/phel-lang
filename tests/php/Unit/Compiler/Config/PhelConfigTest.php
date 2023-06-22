<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Config;

use Gacela\Framework\Gacela;
use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;
use Phel\Config\PhelOutConfig;
use PHPUnit\Framework\TestCase;

final class PhelConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_json_serialize(): void
    {
        $config = (new PhelConfig())
            ->setSrcDirs(['some/directory'])
            ->setTestDirs(['another/directory'])
            ->setVendorDir('vendor')
            ->setOut(
                (new PhelOutConfig())
                    ->setDestDir('out')
                    ->setMainPhelNamespace('test-ns/boot')
                    ->setMainPhpFilename('custom-main'),
            )
            ->setExport(
                (new PhelExportConfig())
                    ->setDirectories(['some/other/dir'])
                    ->setNamespacePrefix('Generated')
                    ->setTargetDirectory('src/Generated'),
            )
            ->setIgnoreWhenBuilding(['src/ignore.me'])
            ->setKeepGeneratedTempFiles(true)
            ->setFormatDirs(['src', 'tests', 'phel'])
        ;

        $expected = [
            'src-dirs' => ['some/directory'],
            'test-dirs' => ['another/directory'],
            'vendor-dir' => 'vendor',
            'out' => [
                'dir' => 'out',
                'main-phel-namespace' => 'test-ns/boot',
                'main-php-filename' => 'custom-main',
                'main-php-path' => __DIR__ . '/out/custom-main.php',
            ],
            'export' => [
                'target-directory' => 'src/Generated',
                'directories' => ['some/other/dir'],
                'namespace-prefix' => 'Generated',
            ],
            'ignore-when-building' => ['src/ignore.me'],
            'keep-generated-temp-files' => true,
            'format-dirs' => ['src', 'tests', 'phel'],
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }
}
