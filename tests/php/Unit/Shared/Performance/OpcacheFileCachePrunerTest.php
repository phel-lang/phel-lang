<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Performance;

use Phel\Shared\Performance\OpcacheFileCache;
use Phel\Shared\Performance\OpcacheFileCachePruner;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function symlink;
use function sys_get_temp_dir;

final class OpcacheFileCachePrunerTest extends TestCase
{
    use RemoveDirTrait;

    private const string CURRENT_ID = 'a0d131c96acbcfdc5ebf8e9b6e5ff55a';

    private const string FOREIGN_ID = 'cf11b0a41c1f745a1bfd730ac5e2b089';

    private string $root;

    private string $cacheDir;

    private OpcacheFileCache $fileCache;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel-opcache-prune-' . bin2hex(random_bytes(6));
        $this->cacheDir = $this->root . '/opcache';
        mkdir($this->cacheDir, 0o755, true);
        $this->fileCache = new OpcacheFileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function test_prunes_a_subtree_whose_system_id_is_not_the_current_one(): void
    {
        $this->plantBin(self::CURRENT_ID, '/opt/app/bin/phel');
        $this->plantBin(self::CURRENT_ID . 'phar:', '///opt/app/phel.phar/bin/phel');
        $this->plantBin(self::FOREIGN_ID, '/opt/app/bin/phel');
        $this->plantBin(self::FOREIGN_ID . 'phar:', '///opt/app/phel.phar/bin/phel');

        $removed = new OpcacheFileCachePruner($this->fileCache)->pruneForeignSystemIds(self::CURRENT_ID);

        self::assertSame([
            $this->cacheDir . '/' . self::FOREIGN_ID,
            $this->cacheDir . '/' . self::FOREIGN_ID . 'phar:',
        ], $removed);
        self::assertDirectoryDoesNotExist($this->cacheDir . '/' . self::FOREIGN_ID);
        self::assertDirectoryDoesNotExist($this->cacheDir . '/' . self::FOREIGN_ID . 'phar:');
        self::assertFileExists($this->cacheDir . '/' . self::CURRENT_ID . '/opt/app/bin/phel.bin');
        self::assertFileExists($this->cacheDir . '/' . self::CURRENT_ID . 'phar:///opt/app/phel.phar/bin/phel.bin');
    }

    public function test_pruning_foreign_ids_keeps_everything_when_the_id_is_unknown(): void
    {
        $this->plantBin(self::FOREIGN_ID, '/opt/app/bin/phel');

        $removed = new OpcacheFileCachePruner($this->fileCache)->pruneForeignSystemIds('');

        self::assertSame([], $removed);
        self::assertFileExists($this->cacheDir . '/' . self::FOREIGN_ID . '/opt/app/bin/phel.bin');
    }

    public function test_prunes_a_bin_whose_source_file_is_gone(): void
    {
        $sourceRoot = $this->root . '/tmp';
        mkdir($sourceRoot, 0o755, true);
        file_put_contents($sourceRoot . '/alive.php', '<?php');

        $this->plantBin(self::CURRENT_ID, $sourceRoot . '/alive.php');
        $this->plantBin(self::CURRENT_ID, $sourceRoot . '/gone.php');

        $removed = new OpcacheFileCachePruner($this->fileCache)
            ->pruneOrphanedBins(self::CURRENT_ID, $sourceRoot);

        self::assertSame(
            [$this->cacheDir . '/' . self::CURRENT_ID . $sourceRoot . '/gone.php.bin'],
            $removed,
        );
        self::assertFileExists($this->cacheDir . '/' . self::CURRENT_ID . $sourceRoot . '/alive.php.bin');
    }

    public function test_pruning_orphaned_bins_removes_the_directories_it_empties(): void
    {
        $sourceRoot = $this->root . '/tmp';
        mkdir($sourceRoot, 0o755, true);

        $this->plantBin(self::CURRENT_ID, $sourceRoot . '/nested/gone.php');

        new OpcacheFileCachePruner($this->fileCache)->pruneOrphanedBins(self::CURRENT_ID, $sourceRoot);

        self::assertDirectoryDoesNotExist($this->cacheDir . '/' . self::CURRENT_ID . $sourceRoot . '/nested');
    }

    public function test_pruning_never_follows_a_symlink_out_of_the_cache_directory(): void
    {
        $outside = $this->root . '/outside';
        mkdir($outside, 0o755, true);
        file_put_contents($outside . '/keep.txt', 'keep');

        mkdir($this->cacheDir . '/' . self::FOREIGN_ID, 0o755, true);
        symlink($outside, $this->cacheDir . '/' . self::FOREIGN_ID . '/escape');

        new OpcacheFileCachePruner($this->fileCache)->pruneForeignSystemIds(self::CURRENT_ID);

        self::assertDirectoryDoesNotExist($this->cacheDir . '/' . self::FOREIGN_ID);
        self::assertFileExists($outside . '/keep.txt');
    }

    public function test_clearing_empties_the_directory_but_keeps_it(): void
    {
        $this->plantBin(self::CURRENT_ID, '/opt/app/bin/phel');
        $this->plantBin(self::FOREIGN_ID, '/opt/app/bin/phel');

        $cleared = new OpcacheFileCachePruner($this->fileCache)->clearContents();

        self::assertTrue($cleared);
        self::assertDirectoryExists($this->cacheDir);
        self::assertSame([], $this->fileCache->entries());
    }

    public function test_clearing_a_missing_directory_reports_nothing_cleared(): void
    {
        $pruner = new OpcacheFileCachePruner(new OpcacheFileCache($this->root . '/absent'));

        self::assertFalse($pruner->clearContents());
    }

    private function plantBin(string $entry, string $sourcePath): void
    {
        $path = $this->cacheDir . '/' . $entry . $sourcePath . OpcacheFileCache::BIN_SUFFIX;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        file_put_contents($path, 'bin');
    }
}
