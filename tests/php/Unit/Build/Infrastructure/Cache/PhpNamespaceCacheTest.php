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
