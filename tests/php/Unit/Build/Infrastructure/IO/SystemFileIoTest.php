<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\IO;

use Phel\Build\Infrastructure\IO\SystemFileIo;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

final class SystemFileIoTest extends TestCase
{
    public function test_get_contents_missing_file_throws_without_warning(): void
    {
        $io = new SystemFileIo();
        $missing = sys_get_temp_dir() . '/' . uniqid('phel-missing-', true) . '.phel';

        // Promote any *reported* PHP warning to an exception. The handler honors
        // error_reporting() (the @-operator masks it out), so a properly suppressed
        // file_get_contents() warning passes, but removing the @ lets E_WARNING
        // through the mask and fails this test.
        // Mimic the production CLI, where E_WARNING is reported; only the
        // @-operator on file_get_contents() should keep it from leaking.
        $previousLevel = error_reporting(E_ALL);
        set_error_handler(static function (int $severity, string $message): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new RuntimeException('Unexpected PHP warning leaked: ' . $message);
        });

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(sprintf('Unable to read file "%s".', $missing));

            $io->getContents($missing);
        } finally {
            restore_error_handler();
            error_reporting($previousLevel);
        }
    }

    public function test_get_contents_reads_existing_file(): void
    {
        $io = new SystemFileIo();
        $path = sys_get_temp_dir() . '/' . uniqid('phel-io-', true) . '.txt';
        $io->putContents($path, 'hello');

        try {
            self::assertSame('hello', $io->getContents($path));
        } finally {
            $io->removeFile($path);
        }
    }
}
