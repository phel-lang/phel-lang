<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Infrastructure\Cache\PhpNamespaceCache;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

final class PhpNamespaceCacheTest extends TestCase
{
    private string $cacheFile = '';

    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phel-cache-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->cacheFile = $this->tmpDir . '/namespace-cache.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }

        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function test_get_returns_null_when_cache_missing(): void
    {
        $cache = new PhpNamespaceCache($this->cacheFile);

        self::assertNull($cache->get('/anything'));
    }

    public function test_put_then_get_returns_entry(): void
    {
        $cache = new PhpNamespaceCache($this->cacheFile);
        $entry = new NamespaceCacheEntry('/x.phel', 123, 'x', [], true);

        $cache->put('/x.phel', $entry);

        self::assertSame($entry, $cache->get('/x.phel'));
    }

    public function test_load_evicts_entries_whose_path_matches_always_excluded_segments(): void
    {
        $this->writeCacheFile([
            '/repo/src/phel/util.phel' => [
                'mtime' => 100,
                'namespace' => 'phel.util',
                'dependencies' => [],
                'isPrimaryDefinition' => true,
            ],
            '/repo/.claude/worktrees/a/src/phel/util.phel' => [
                'mtime' => 100,
                'namespace' => 'phel.util',
                'dependencies' => [],
                'isPrimaryDefinition' => true,
            ],
            '/repo/vendor/foo/bar.phel' => [
                'mtime' => 100,
                'namespace' => 'foo.bar',
                'dependencies' => [],
                'isPrimaryDefinition' => true,
            ],
        ]);

        $cache = new PhpNamespaceCache($this->cacheFile);

        self::assertNotNull($cache->get('/repo/src/phel/util.phel'));
        self::assertNull($cache->get('/repo/.claude/worktrees/a/src/phel/util.phel'));
        self::assertNull($cache->get('/repo/vendor/foo/bar.phel'));
        self::assertSame(['/repo/src/phel/util.phel'], $cache->getAllFiles());
    }

    /**
     * The `phel doc` / LSP / REPL-completion path scans a unique
     * `.phel_temp_<uniqid>` directory and removes it again before the shutdown
     * flush, so the entry it produces can never be read back. Re-persisting it
     * on every run is what grew this cache to 2619 entries, 87% of them dead
     * (#3007).
     */
    public function test_save_drops_entries_whose_file_no_longer_exists(): void
    {
        $this->writeCacheFile([
            $this->tmpDir . '/gone.phel' => [
                'mtime' => 100,
                'namespace' => 'gone',
                'dependencies' => [],
                'isPrimaryDefinition' => true,
            ],
        ]);
        $liveFile = $this->tmpDir . '/live.phel';
        file_put_contents($liveFile, '(ns live)');

        $cache = new PhpNamespaceCache($this->cacheFile);
        $cache->put($liveFile, new NamespaceCacheEntry($liveFile, 100, 'live', [], true));
        $cache->save();

        $reloaded = new PhpNamespaceCache($this->cacheFile);

        self::assertSame([$liveFile], $reloaded->getAllFiles());

        unlink($liveFile);
    }

    /**
     * A file that is still there but whose `mtime` moved on stays reachable:
     * the next scan overwrites it under the same key, so only a missing file
     * makes an entry unreachable.
     */
    public function test_save_keeps_an_entry_whose_file_exists_but_is_out_of_date(): void
    {
        $staleFile = $this->tmpDir . '/stale.phel';
        file_put_contents($staleFile, '(ns stale)');
        $this->writeCacheFile([
            $staleFile => [
                'mtime' => 1,
                'namespace' => 'stale',
                'dependencies' => [],
                'isPrimaryDefinition' => true,
            ],
        ]);

        $cache = new PhpNamespaceCache($this->cacheFile);
        $cache->put('/other.phel', new NamespaceCacheEntry('/other.phel', 100, 'other', [], true));
        $cache->save();

        $reloaded = new PhpNamespaceCache($this->cacheFile);

        self::assertNotNull($reloaded->get($staleFile));

        unlink($staleFile);
    }

    /**
     * @param array<string, array{mtime: int, namespace: string, dependencies: list<string>, isPrimaryDefinition: bool}> $entries
     */
    private function writeCacheFile(array $entries): void
    {
        $payload = [
            'version' => '1.0',
            'entries' => $entries,
        ];

        file_put_contents(
            $this->cacheFile,
            '<?php return ' . var_export($payload, true) . ';',
        );
    }
}
