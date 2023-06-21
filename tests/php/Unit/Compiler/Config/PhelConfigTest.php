<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Config;

use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;
use PHPUnit\Framework\TestCase;

final class PhelConfigTest extends TestCase
{
    public function test_json_serialize(): void
    {
        $config = (new PhelConfig())
            ->setSrcDirs(['some/directory'])
            ->setTestDirs(['another/directory'])
            ->setVendorDir('vendor')
            ->setOutDir('out')
            ->setOutMainNs('test-ns/boot')
            ->setOutMainFilename('custom-main')
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
            'out-dir' => 'out',
            'out-main-ns' => 'test-ns/boot',
            'out-main-filename' => 'custom-main',
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
