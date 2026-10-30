<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Phel\Build\Infrastructure\Cache\CacheDirectory;
use Phel\Build\Infrastructure\Cache\CacheIndexFile;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;
use function extension_loaded;

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

    public function test_merging_the_disk_index_does_not_create_write_include_or_remove_a_temporary_php_file(): void
    {
        $process = Subprocess::run([PHP_BINARY, '-r', <<<'PHP'
        namespace Phel\Build\Infrastructure\Cache {
            function tempnam(string $directory, string $prefix): string|false {
                ++$GLOBALS['operations']['tempnam'];
                return \tempnam($directory, $prefix);
            }
            function file_put_contents(string $path, string $contents): int|false {
                ++$GLOBALS['operations']['write'];
                return \file_put_contents($path, $contents);
            }
            function unlink(string $path): bool {
                ++$GLOBALS['operations']['unlink'];
                return \unlink($path);
            }
        }
        namespace {
            require $argv[1];
            $index = new \Phel\Build\Infrastructure\Cache\CacheIndexFile(new \Phel\Build\Infrastructure\Cache\CacheDirectory($argv[2]), 'test');
            $entry = ['namespace' => 'one', 'source_hash' => 'hash', 'compiled_path' => '/compiled/one.php', 'last_accessed' => 1, 'deprecations' => []];
            $GLOBALS['operations'] = ['tempnam' => 0, 'write' => 0, 'unlink' => 0];
            $index->save(['/a.phel' => $entry], []);
            $GLOBALS['operations'] = ['tempnam' => 0, 'write' => 0, 'unlink' => 0];
            $included = get_included_files();
            $merged = $index->save(['/b.phel' => $entry], []);
            $GLOBALS['operations']['include'] = count(array_filter(array_diff(get_included_files(), $included), static fn(string $path): bool => str_contains($path, 'phel_cache_merge_')));
            echo json_encode(['operations' => $GLOBALS['operations'], 'entries' => array_keys($merged)]);
        }
        PHP, dirname(__DIR__, 6) . '/vendor/autoload.php', $this->cacheDir]);

        self::assertSame(0, $process->exitCode, $process->stderr);
        $result = json_decode($process->stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['tempnam' => 0, 'write' => 0, 'unlink' => 0, 'include' => 0], $result['operations']);
        self::assertSame(['/a.phel', '/b.phel'], $result['entries']);
    }

    public function test_save_drops_tombstoned_entries_from_disk(): void
    {
        $this->index()->save(['/a.phel' => $this->entry('a')], []);

        $merged = $this->index()->save(['/b.phel' => $this->entry('b')], ['/a.phel' => true]);

        self::assertSame(['/b.phel'], array_keys($merged));
    }

    public function test_merge_uses_the_read_snapshot_when_the_path_is_replaced_after_the_read(): void
    {
        $process = Subprocess::run([PHP_BINARY, '-r', <<<'PHP'
        namespace Phel\Build\Infrastructure\Cache {
            function file_get_contents(string $path): string|false {
                $snapshot = \file_get_contents($path);
                if (isset($GLOBALS['replacement'])) {
                    \file_put_contents($path, $GLOBALS['replacement']);
                }
                return $snapshot;
            }
        }
        namespace {
            require $argv[1];
            $index = new \Phel\Build\Infrastructure\Cache\CacheIndexFile(new \Phel\Build\Infrastructure\Cache\CacheDirectory($argv[2]), 'test');
            $entry = ['namespace' => 'one', 'source_hash' => 'hash', 'compiled_path' => '/compiled/one.php', 'last_accessed' => 1, 'deprecations' => []];
            $index->save(['/a.phel' => $entry], []);
            $GLOBALS['replacement'] = '<?php return ' . var_export(['version' => $index->version(), 'entries' => ['/replacement.phel' => $entry]], true) . ';';
            echo json_encode(array_keys($index->save(['/b.phel' => $entry], [])));
        }
        PHP, dirname(__DIR__, 6) . '/vendor/autoload.php', $this->cacheDir]);

        self::assertSame(0, $process->exitCode, $process->stderr);
        self::assertSame(['/a.phel', '/b.phel'], json_decode($process->stdout, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_save_recovers_from_a_torn_index(): void
    {
        mkdir($this->cacheDir, 0o777, true);
        file_put_contents($this->cacheDir . '/compiled-index.php', "<?php return ['version' => '");

        self::assertSame(['/b.phel' => $this->entry('b')], $this->index()->save(['/b.phel' => $this->entry('b')], []));
    }

    public function test_merge_uses_current_bytes_even_when_opcache_keeps_an_older_index(): void
    {
        if (!extension_loaded('Zend OPcache')) {
            self::markTestSkipped('OPcache is not installed.');
        }

        $process = Subprocess::run([PHP_BINARY, '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.file_update_protection=0', '-r', <<<'PHP'
        require $argv[1];
        $index = new \Phel\Build\Infrastructure\Cache\CacheIndexFile(new \Phel\Build\Infrastructure\Cache\CacheDirectory($argv[2]), 'test');
        $entry = ['namespace' => 'one', 'source_hash' => 'hash', 'compiled_path' => '/compiled/one.php', 'last_accessed' => 1, 'deprecations' => []];
        $index->save(['/a.phel' => $entry], []);
        $file = $argv[2] . '/compiled-index.php';
        opcache_compile_file($file);
        file_put_contents($file, '<?php return ' . var_export(['version' => $index->version(), 'entries' => ['/current.phel' => $entry]], true) . ';');
        $stale = include $file;
        echo json_encode(['stale' => array_keys($stale['entries']), 'merged' => array_keys($index->save(['/b.phel' => $entry], []))]);
        PHP, dirname(__DIR__, 6) . '/vendor/autoload.php', $this->cacheDir]);

        self::assertSame(0, $process->exitCode, $process->stderr);
        $result = json_decode($process->stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['/a.phel'], $result['stale']);
        self::assertSame(['/current.phel', '/b.phel'], $result['merged']);
    }

    public function test_merge_preserves_exported_literal_bytes(): void
    {
        $path = "/quote'\\dollar\$é\0.phel";
        $entry = $this->entry('one');
        $entry['deprecations'] = [['message' => "quote'\\dollar\$é\0\n", 'announced' => true]];
        $this->index()->save([$path => $entry], []);

        $merged = $this->index()->save(['/b.phel' => $this->entry('b')], []);

        self::assertSame($entry, $merged[$path]);
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
