<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Phel\Build\Infrastructure\Cache\PhpScanIndexCache;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_put_contents;
use function filemtime;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

final class PhpScanIndexCacheTest extends TestCase
{
    private string $cacheFile = '';

    private string $tmpDir = '';

    private string $liveDir = '';

    private string $liveFile = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phel-scan-index-cache-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->cacheFile = $this->tmpDir . '/scan-index.php';

        $this->liveDir = $this->tmpDir . '/live';
        mkdir($this->liveDir, 0777, true);
        $this->liveFile = $this->liveDir . '/a.phel';
        file_put_contents($this->liveFile, '(ns a)');
    }

    protected function tearDown(): void
    {
        foreach ([$this->cacheFile, $this->liveFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        foreach ([$this->liveDir, $this->tmpDir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function test_put_then_get_returns_entry(): void
    {
        $cache = new PhpScanIndexCache($this->cacheFile);

        $cache->put($this->liveDir, $this->fingerprintOfLiveDir(), [$this->liveInfo()]);

        self::assertNotNull($cache->get($this->liveDir));
    }

    /**
     * `phel doc`, LSP hover and REPL completion each scan a unique
     * `.phel_temp_<uniqid>` directory and delete it again before the shutdown
     * flush runs, so the entry they produce is unreachable the moment it is
     * written: the key embeds the removed path and can never be produced
     * again. Persisting it only grows the index (#3007).
     */
    public function test_save_drops_entries_whose_directory_no_longer_exists(): void
    {
        $deadDir = $this->tmpDir . '/gone';
        $this->writeCacheFile([
            $deadDir => $this->serializedEntry($deadDir, $deadDir . '/doc.phel'),
        ]);

        $cache = new PhpScanIndexCache($this->cacheFile);
        $cache->put($this->liveDir, $this->fingerprintOfLiveDir(), [$this->liveInfo()]);
        $cache->save();

        $reloaded = new PhpScanIndexCache($this->cacheFile);

        self::assertNull($reloaded->get($deadDir));
        self::assertNotNull($reloaded->get($this->liveDir));
    }

    /**
     * Only a missing directory makes an entry unreachable. A directory that is
     * still there but whose contents changed stays reachable under the same
     * key, and the next scan overwrites it, so it must survive the prune.
     */
    public function test_save_keeps_an_entry_whose_directory_exists_but_is_out_of_date(): void
    {
        $this->writeCacheFile([
            $this->liveDir => $this->serializedEntry($this->liveDir, $this->liveFile, mtime: 1),
        ]);

        $cache = new PhpScanIndexCache($this->cacheFile);
        $cache->put('another-key', $this->fingerprintOfLiveDir(), [$this->liveInfo()]);
        $cache->save();

        $reloaded = new PhpScanIndexCache($this->cacheFile);

        self::assertNotNull($reloaded->get($this->liveDir));
    }

    private function liveInfo(): NamespaceInformation
    {
        return new NamespaceInformation($this->liveFile, 'a', [], true);
    }

    /**
     * @return array<string, array{mtime: int, fileCount: int}>
     */
    private function fingerprintOfLiveDir(): array
    {
        return [$this->liveDir => ['mtime' => (int) filemtime($this->liveDir), 'fileCount' => 1]];
    }

    /**
     * @return array{perDir: array<string, array{mtime: int, fileCount: int}>, files: list<array{file: string, mtime: int}>, infos: list<array{file: string, namespace: string, dependencies: list<string>, isPrimaryDefinition: bool}>}
     */
    private function serializedEntry(string $dir, string $file, int $mtime = 100): array
    {
        return [
            'perDir' => [$dir => ['mtime' => $mtime, 'fileCount' => 1]],
            'files' => [['file' => $file, 'mtime' => $mtime]],
            'infos' => [[
                'file' => $file,
                'namespace' => 'doc',
                'dependencies' => [],
                'isPrimaryDefinition' => true,
            ]],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $entries
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
