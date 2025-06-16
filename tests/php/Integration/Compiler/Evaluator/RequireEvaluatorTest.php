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

    protected function setUp(): void
    {
        RealFilesystem::reset();
        $this->filesystem = new FilesystemFacade();
        $this->evaluator = new RequireEvaluator($this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->clearAll();
    }

    public function test_it_creates_missing_temp_directory(): void
    {
        $tempDir = sys_get_temp_dir() . '/phel-test-' . uniqid('', true);
        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($tempDir): void {
            $config->addAppConfigKeyValue(PhelConfig::TEMP_DIR, $tempDir);
        });

        $result = $this->evaluator->eval('return 42;');

        self::assertSame(42, $result);
        self::assertDirectoryExists($tempDir);

        rmdir($tempDir);
    }
}
