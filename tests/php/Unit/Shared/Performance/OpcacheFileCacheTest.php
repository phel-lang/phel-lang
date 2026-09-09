<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Performance;

use Phel\Shared\Performance\OpcacheFileCache;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class OpcacheFileCacheTest extends TestCase
{
    use RemoveDirTrait;

    private const string CURRENT_ID = 'a0d131c96acbcfdc5ebf8e9b6e5ff55a';

    private const string FOREIGN_ID = 'cf11b0a41c1f745a1bfd730ac5e2b089';

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-opcache-cache-' . bin2hex(random_bytes(6));
        mkdir($this->cacheDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    public function test_missing_directory_reports_nothing(): void
    {
        $fileCache = new OpcacheFileCache($this->cacheDir . '/absent');

        self::assertFalse($fileCache->exists());
        self::assertSame([], $fileCache->entries());
        self::assertSame(0, $fileCache->sizeInBytes());
        self::assertSame('', $fileCache->detectSystemId('/any/file.php'));
    }

    public function test_detects_the_system_id_from_the_bin_of_a_compiled_file(): void
    {
        $this->plantBin(self::FOREIGN_ID, '/opt/other/boot.php');
        $this->plantBin(self::CURRENT_ID, '/opt/app/bin/phel');

        $fileCache = new OpcacheFileCache($this->cacheDir);

        self::assertSame(self::CURRENT_ID, $fileCache->detectSystemId('/opt/app/bin/phel'));
    }

    public function test_detects_the_system_id_of_a_phar_entry(): void
    {
        // A `phar:///x` source path contributes its own `phar:` segment, so the
        // top-level entry is the id with that suffix glued on.
        $this->plantBin(self::CURRENT_ID . 'phar:', '///opt/app/phel.phar/bin/phel');

        $fileCache = new OpcacheFileCache($this->cacheDir);

        self::assertSame(self::CURRENT_ID, $fileCache->detectSystemId('phar:///opt/app/phel.phar/bin/phel'));
    }

    public function test_detection_returns_empty_when_no_subtree_holds_the_file(): void
    {
        $this->plantBin(self::FOREIGN_ID, '/opt/other/boot.php');

        $fileCache = new OpcacheFileCache($this->cacheDir);

        self::assertSame('', $fileCache->detectSystemId('/opt/app/bin/phel'));
    }

    public function test_foreign_entries_keep_the_current_id_and_its_phar_sibling(): void
    {
        $this->plantBin(self::CURRENT_ID, '/opt/app/bin/phel');
        $this->plantBin(self::CURRENT_ID . 'phar:', '///opt/app/phel.phar/bin/phel');
        $this->plantBin(self::FOREIGN_ID, '/opt/app/bin/phel');
        $this->plantBin(self::FOREIGN_ID . 'phar:', '///opt/app/phel.phar/bin/phel');

        $fileCache = new OpcacheFileCache($this->cacheDir);

        self::assertSame([
            $this->cacheDir . '/' . self::FOREIGN_ID,
            $this->cacheDir . '/' . self::FOREIGN_ID . 'phar:',
        ], $fileCache->foreignEntries(self::CURRENT_ID));
    }

    public function test_size_sums_every_bin_below_the_directory(): void
    {
        $this->plantBin(self::CURRENT_ID, '/opt/app/bin/phel', 'seven..');
        $this->plantBin(self::FOREIGN_ID, '/opt/app/bin/phel', 'three');

        $fileCache = new OpcacheFileCache($this->cacheDir);

        self::assertSame(12, $fileCache->sizeInBytes());
    }

    private function plantBin(string $entry, string $sourcePath, string $content = 'bin'): void
    {
        $path = $this->cacheDir . '/' . $entry . $sourcePath . OpcacheFileCache::BIN_SUFFIX;
        mkdir(dirname($path), 0o755, true);
        file_put_contents($path, $content);
    }
}
