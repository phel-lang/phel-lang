<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Phel\Build\Infrastructure\Cache\CacheDirectory;
use Phel\Build\Infrastructure\Cache\CacheIndexFile;
use PHPUnit\Framework\TestCase;

final class CacheIndexFileTest extends TestCase
{
    private string $cacheDir = '';

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-cache-index-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        removeDirectory($this->cacheDir);
    }

    public function test_a_torn_index_reads_as_empty(): void
    {
        mkdir($this->cacheDir, 0o777, true);
        file_put_contents($this->cacheDir . '/compiled-index.php', "<?php return ['version' => '1', 'entries' => ['/x.phel' => ['namesp");

        self::assertSame([], $this->index()->load());
    }

    public function test_save_replaces_the_index_instead_of_rewriting_it(): void
    {
        // A reader includes the index without a lock, so it must only ever see
        // a whole file: the old one or the new one.
        $index = $this->index();
        $index->save(['/a.phel' => $this->entry('a')], []);

        $before = fileinode($this->cacheDir . '/compiled-index.php');

        $index->save(['/b.phel' => $this->entry('b')], []);
        clearstatcache();

        self::assertNotSame($before, fileinode($this->cacheDir . '/compiled-index.php'));
        self::assertSame([], glob($this->cacheDir . '/*.tmp'));
    }

    public function test_save_keeps_the_entries_another_instance_wrote(): void
    {
        $this->index()->save(['/a.phel' => $this->entry('a')], []);

        $merged = $this->index()->save(['/b.phel' => $this->entry('b')], []);

        self::assertSame(['/a.phel', '/b.phel'], array_keys($merged));
        self::assertSame(['/a.phel', '/b.phel'], array_keys($this->index()->load()));
    }

    public function test_save_drops_tombstoned_entries_from_disk(): void
    {
        $this->index()->save(['/a.phel' => $this->entry('a')], []);

        $merged = $this->index()->save(['/b.phel' => $this->entry('b')], ['/a.phel' => true]);

        self::assertSame(['/b.phel'], array_keys($merged));
    }

    private function index(): CacheIndexFile
    {
        return new CacheIndexFile(new CacheDirectory($this->cacheDir), 'test');
    }

    /**
     * @return array{namespace: string, source_hash: string, compiled_path: string, last_accessed: int, deprecations: list<array{message: string, announced: bool}>}
     */
    private function entry(string $namespace): array
    {
        return [
            'namespace' => $namespace,
            'source_hash' => 'hash-' . $namespace,
            'compiled_path' => '/compiled/' . $namespace . '.php',
            'last_accessed' => 1,
            'deprecations' => [],
        ];
    }
}
