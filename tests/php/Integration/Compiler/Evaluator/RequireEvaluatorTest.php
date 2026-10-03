<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Evaluator;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Compiler\Domain\Evaluator\RequireEvaluator;
use Phel\Config\PhelConfig;
use Phel\Filesystem\FilesystemFacade;
use Phel\Filesystem\Infrastructure\RealFilesystem;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

final class RequireEvaluatorTest extends TestCase
{
    use RemoveDirTrait;

    private RequireEvaluator $evaluator;

    private FilesystemFacade $filesystem;

    private string $tempDir = '';

    protected function setUp(): void
    {
        RealFilesystem::reset();
        $this->filesystem = new FilesystemFacade();
        $this->evaluator = new RequireEvaluator($this->filesystem);
        RequireEvaluator::clearCache();
    }

    protected function tearDown(): void
    {
        $this->filesystem->clearAll();

        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
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

    /**
     * The temp dir is shared by every process. Another process's empty or
     * half-written file for the same code must not be what this one requires
     * (#3495).
     */
    public function test_the_file_it_requires_belongs_to_this_process(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::TEMP_DIR, $this->tempDir);
        });

        $hash = md5("<?php\nreturn 42;");
        file_put_contents(sprintf('%s/__phel_%d_%s.php', $this->tempDir, getmypid() + 1, $hash), '');

        self::assertSame(42, $this->evaluator->eval('return 42;'));
        self::assertFileExists(sprintf('%s/__phel_%d_%s.php', $this->tempDir, getmypid(), $hash));
    }
}
