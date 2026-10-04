<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Compile\Output;

use Phel;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFile;
use Phel\Build\Domain\Compile\Output\NamespacePathTransformer;
use Phel\Config\PhelBuildConfig;
use Phel\Shared\VersionResolver;
use PHPUnit\Framework\TestCase;

final class EntryPointPhpFileTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-entrypoint-' . uniqid();
        mkdir($this->tempDir . '/out', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir . '/out/main.php');
        @rmdir($this->tempDir . '/out');
        @rmdir($this->tempDir);
    }

    public function test_multi_segment_namespace_points_at_nested_path(): void
    {
        $buildConfig = new PhelBuildConfig(
            mainPhelNamespace: 'cli-skeleton.main',
            mainPhpPath: 'out/main.php',
        );

        $entry = new EntryPointPhpFile(
            $buildConfig,
            new NamespacePathTransformer(),
            $this->tempDir,
        );

        $entry->createFile();

        $contents = (string) file_get_contents($this->tempDir . '/out/main.php');

        self::assertStringContainsString(
            '$compiledFile = __DIR__ . "/cli_skeleton/main.php";',
            $contents,
        );
        self::assertStringContainsString(Phel::class . "::assertBuiltWith('" . new VersionResolver()->resolve() . "');", $contents);
        self::assertStringContainsString(Phel::class . '::setupRuntimeArgs(', $contents);
        self::assertLessThan(
            strpos($contents, Phel::class . '::bootstrap('),
            strpos($contents, Phel::class . '::assertBuiltWith('),
        );
        self::assertLessThan(
            strpos($contents, Phel::class . '::assertBuiltWith('),
            strpos($contents, 'require_once dirname(__DIR__)'),
        );
    }
}
