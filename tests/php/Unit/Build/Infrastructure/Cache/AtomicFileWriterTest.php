<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Phel\Build\Infrastructure\Cache\AtomicFileWriter;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function chmod;
use function file_get_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class AtomicFileWriterTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        // Unique dir per test so Gacela's per-dir writability memoization
        // never carries a verdict across tests.
        $this->baseDir = sys_get_temp_dir() . '/phel-atomic-' . bin2hex(random_bytes(6));
        mkdir($this->baseDir, 0755, true);
    }

    protected function tearDown(): void
    {
        @chmod($this->baseDir, 0755);
        @unlink($this->baseDir . '/out.php');
        @rmdir($this->baseDir);
    }

    public function test_writes_content_atomically(): void
    {
        $path = $this->baseDir . '/out.php';

        self::assertTrue(new AtomicFileWriter()->write($path, '<?php return 1;'));
        self::assertSame('<?php return 1;', file_get_contents($path));
    }

    public function test_returns_false_without_warning_when_dir_is_read_only(): void
    {
        chmod($this->baseDir, 0555);
        if (@mkdir($this->baseDir . '/probe')) {
            @rmdir($this->baseDir . '/probe');
            self::markTestSkipped('chmod has no effect (running as root?)');
        }

        // No temp file left behind, no raw PHP warning — a read-only cache dir
        // is an expected state, not an error.
        self::assertFalse(new AtomicFileWriter()->write($this->baseDir . '/out.php', '<?php return 1;'));
        self::assertFileDoesNotExist($this->baseDir . '/out.php');
    }
}
