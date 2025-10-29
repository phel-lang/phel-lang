<?php

declare(strict_types=1);

namespace PhelTest\Integration\Filesystem;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Config\PhelConfig;
use Phel\Filesystem\FilesystemFacade;
use Phel\Filesystem\Infrastructure\RealFilesystem;
use Phel\Phel;
use PHPUnit\Framework\TestCase;

final class FilesystemFacadeTest extends TestCase
{
    private FilesystemFacade $filesystem;

    protected function setUp(): void
    {
        RealFilesystem::reset();
        $this->filesystem = new FilesystemFacade();
    }

    public function test_remove_generated_files_after_clear_all_by_default(): void
    {
        Phel::bootstrap(__DIR__);

        $filename = tempnam(sys_get_temp_dir(), '__test');
        $this->filesystem->addFile($filename);

        self::assertFileExists($filename);
        $this->filesystem->clearAll();
        self::assertFileDoesNotExist($filename);
    }

    public function test_remove_generated_files_after_clear_all(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::KEEP_GENERATED_TEMP_FILES, false);
        });

        $filename = tempnam(sys_get_temp_dir(), '__test');
        $this->filesystem->addFile($filename);

        self::assertFileExists($filename);
        $this->filesystem->clearAll();
        self::assertFileDoesNotExist($filename);
    }

    public function test_keep_generated_files_after_clear_all(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::KEEP_GENERATED_TEMP_FILES, true);
        });

        $filename = tempnam(sys_get_temp_dir(), '__test');
        $this->filesystem->addFile($filename);

        self::assertFileExists($filename);
        $this->filesystem->clearAll();
        self::assertFileExists($filename);
        unlink($filename);
    }
}
