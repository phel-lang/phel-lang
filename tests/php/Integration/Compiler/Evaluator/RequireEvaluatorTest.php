<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Evaluator;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Compiler\Domain\Evaluator\RequireEvaluator;
use Phel\Config\PhelConfig;
use Phel\Filesystem\FilesystemFacade;
use Phel\Filesystem\Infrastructure\RealFilesystem;
use PHPUnit\Framework\TestCase;

final class RequireEvaluatorTest extends TestCase
{
    private RequireEvaluator $evaluator;

    private FilesystemFacade $filesystem;

    private string $tempDir = '';

    protected function setUp(): void
    {
        RealFilesystem::reset();
        $this->filesystem = new FilesystemFacade();
        $this->evaluator = new RequireEvaluator($this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->clearAll();

        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            $this->deleteDirRecursive($this->tempDir);
        }
    }

    public function test_it_creates_missing_temp_directory(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-test-' . uniqid('', true);
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::TEMP_DIR, $this->tempDir);
        });

        $result = $this->evaluator->eval('return 42;');

        self::assertSame(42, $result);
        self::assertDirectoryExists($this->tempDir);
    }

    private function deleteDirRecursive(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirRecursive($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
