<?php

declare(strict_types=1);

namespace PhelTest\Integration\Filesystem;

use Gacela\Framework\Gacela;
use Phel\Filesystem\FilesystemFacade;
use Phel\Filesystem\Infrastructure\FilesystemSingleton;
use PHPUnit\Framework\TestCase;

final class FilesystemTest extends TestCase
{
    private FilesystemFacade $filesystem;

    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
        FilesystemSingleton::reset();
    }

    public function setUp(): void
    {
        $this->filesystem = new FilesystemFacade();
    }

    public function test_clear_all(): void
    {
        $filename = tempnam(sys_get_temp_dir(), '__test');
        $this->filesystem->addFile($filename);

        self::assertFileExists($filename);
        $this->filesystem->clearAll();
        self::assertFileDoesNotExist($filename);
    }
}
