<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Config;

use Phel\Config\PhelExportConfig;
use PHPUnit\Framework\TestCase;

final class PhelExportConfigTest extends TestCase
{
    public function test_default(): void
    {
        $config = new PhelExportConfig();

        $expected = [
            PhelExportConfig::TARGET_DIRECTORY => 'src/PhelGenerated',
            PhelExportConfig::FROM_DIRECTORIES => ['src'],
            PhelExportConfig::NAMESPACE_PREFIX => 'PhelGenerated',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }

    public function test_named_arg_constructor(): void
    {
        $config = new PhelExportConfig(
            fromDirectories: ['lib'],
            namespacePrefix: 'MyGen',
            targetDirectory: 'gen',
        );

        self::assertSame(['lib'], $config->fromDirectories);
        self::assertSame('MyGen', $config->namespacePrefix);
        self::assertSame('gen', $config->targetDirectory);
    }

    public function test_with_methods_are_immutable(): void
    {
        $original = new PhelExportConfig();
        $updated = $original->withNamespacePrefix('MyGen');

        self::assertNotSame($original, $updated);
        self::assertSame('PhelGenerated', $original->namespacePrefix);
        self::assertSame('MyGen', $updated->namespacePrefix);
    }

    public function test_with_methods_chain(): void
    {
        $config = new PhelExportConfig()
            ->withFromDirectories(['lib/phel'])
            ->withNamespacePrefix('Generated')
            ->withTargetDirectory('out');

        $expected = [
            PhelExportConfig::TARGET_DIRECTORY => 'out',
            PhelExportConfig::FROM_DIRECTORIES => ['lib/phel'],
            PhelExportConfig::NAMESPACE_PREFIX => 'Generated',
        ];

        self::assertSame($expected, $config->jsonSerialize());
    }
}
