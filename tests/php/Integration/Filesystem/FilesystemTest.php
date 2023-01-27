<?php

declare(strict_types=1);

namespace PhelTest\Integration\Filesystem;

use Phel\Filesystem\FilesystemFacade;
use PHPUnit\Framework\TestCase;

final class FilesystemTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        FilesystemFacade::reset();
    }

    public function test_clear_all(): void
    {
        $filesystem = FilesystemFacade::getInstance();
        $filename = tempnam(sys_get_temp_dir(), '__test');
        $filesystem->addFile($filename);

        self::assertFileExists($filename);
        $filesystem->clearAll();
        self::assertFileDoesNotExist($filename);
    }
}
