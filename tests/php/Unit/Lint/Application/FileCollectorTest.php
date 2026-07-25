<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application;

use Phel\Lint\Application\FileCollector;
use Phel\Lint\Domain\Exception\LintSourceException;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function chmod;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class FileCollectorTest extends TestCase
{
    private string $baseDir = '';

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/phel-file-collector-' . uniqid();
        mkdir($this->baseDir, 0777, true);
    }

    protected function tearDown(): void
    {
        @chmod($this->baseDir . '/locked', 0755);
        @unlink($this->baseDir . '/locked/a.phel');
        @rmdir($this->baseDir . '/locked');
        @unlink($this->baseDir . '/a.phel');
        @unlink($this->baseDir . '/b.txt');
        @rmdir($this->baseDir);
    }

    public function test_it_collects_phel_files_from_a_directory(): void
    {
        file_put_contents($this->baseDir . '/a.phel', "(ns a)\n");
        file_put_contents($this->baseDir . '/b.txt', "not phel\n");

        $files = new FileCollector()->collect([$this->baseDir]);

        self::assertCount(1, $files);
        self::assertStringEndsWith('/a.phel', $files[0]);
    }

    public function test_it_skips_a_path_that_does_not_exist(): void
    {
        self::assertSame([], new FileCollector()->collect([$this->baseDir . '/nope']));
    }

    /**
     * An unreadable directory used to yield zero files, which made
     * `phel lint <dir>` print "No lint issues found" and exit 0. That is the
     * exact failure `LintSourceException` exists to prevent for a single
     * unreadable file, so the directory case raises too.
     */
    public function test_it_raises_instead_of_reporting_an_unreadable_directory_as_clean(): void
    {
        $dir = $this->baseDir . '/locked';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/a.phel', "(ns a)\n");
        chmod($dir, 0000);

        if ($this->isStillReadable($dir)) {
            self::markTestSkipped('chmod has no effect (running as root?)');
        }

        $this->expectException(LintSourceException::class);
        $this->expectExceptionMessage('Cannot read directory to lint: ');
        $this->expectExceptionMessageMatches('#locked$#');

        new FileCollector()->collect([$dir]);
    }

    public function test_the_directory_failure_chains_the_underlying_iterator_error(): void
    {
        $dir = $this->baseDir . '/locked';
        mkdir($dir, 0777, true);
        chmod($dir, 0000);

        if ($this->isStillReadable($dir)) {
            self::markTestSkipped('chmod has no effect (running as root?)');
        }

        try {
            new FileCollector()->collect([$dir]);
            self::fail('Expected a LintSourceException');
        } catch (LintSourceException $lintSourceException) {
            self::assertInstanceOf(UnexpectedValueException::class, $lintSourceException->getPrevious());
        }
    }

    private function isStillReadable(string $dir): bool
    {
        return is_dir($dir) && @scandir($dir) !== false;
    }
}
