<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Config;

use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;
use Phel\Config\PhelBuildConfig;
use PHPUnit\Framework\TestCase;

final class PhelConfigTest extends TestCase
{
    public function test_json_serialize(): void
    {
        $config = (new PhelConfig())
            ->setSrcDirs(['some/directory'])
            ->setTestDirs(['another/directory'])
            ->setVendorDir('vendor')
            ->setErrorLogFile('error-log.file')
            ->setBuildConfig((new PhelBuildConfig())
                ->setMainPhpPath('out/custom-index.php')
                ->setMainPhelNamespace('test-ns/boot'), )
            ->setExport((new PhelExportConfig())
                ->setDirectories(['some/other/dir'])
                ->setNamespacePrefix('Generated')
                ->setTargetDirectory('src/Generated'))
            ->setIgnoreWhenBuilding(['src/ignore.me'])
            ->setNoCacheWhenBuilding(['should-not-be-cached'])
            ->setKeepGeneratedTempFiles(true)
            ->setFormatDirs(['src', 'tests', 'phel']);

        $expected = [
            'src-dirs' => ['some/directory'],
            'test-dirs' => ['another/directory'],
            'vendor-dir' => 'vendor',
            'error-log-file' => 'error-log.file',
            'out' => [
                'main-phel-namespace' => 'test-ns/boot',
                'dir' => 'out',
                'main-php-filename' => 'custom-index.php',
                'main-php-path' => 'out/custom-index.php',
            ],
            'export' => [
                'target-directory' => 'src/Generated',
                'directories' => ['some/other/dir'],
                'namespace-prefix' => 'Generated',
            ],
            'ignore-when-building' => ['src/ignore.me'],
            'no-cache-when-building' => ['should-not-be-cached'],
            'keep-generated-temp-files' => true,
            'format-dirs' => ['src', 'tests', 'phel'],
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }
}
