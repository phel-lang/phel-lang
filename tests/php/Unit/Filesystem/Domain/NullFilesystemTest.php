<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Domain;

use Phel\Filesystem\Domain\NullFilesystem;
use PHPUnit\Framework\TestCase;

final class NullFilesystemTest extends TestCase
{
    public function test_add_file_and_clear_all_are_no_ops(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'phel-nullfs-');
        self::assertIsString($file);

        $filesystem = new NullFilesystem();
        $filesystem->addFile($file);
        $filesystem->clearAll();

        // clearAll() must not touch any tracked file: the path still exists.
        self::assertFileExists($file);

        unlink($file);
    }
}
