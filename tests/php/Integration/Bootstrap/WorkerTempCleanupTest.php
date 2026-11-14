<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;

final class WorkerTempCleanupTest extends TestCase
{
    use RemoveDirTrait;

    public function test_worker_cleanup_runs_after_deferred_cache_writes(): void
    {
        $root = sys_get_temp_dir() . '/phel-worker-cleanup-' . bin2hex(random_bytes(6));
        mkdir($root, 0o700, true);

        try {
            $result = Subprocess::run([
                PHP_BINARY,
                '-r',
                <<<'PHP'
                require $argv[1] . '/tests/bootstrap.php';
                $root = getenv('TMPDIR');
                $cache = new \Phel\Build\Infrastructure\Cache\CompiledCodeCache($root . '/cache');
                $cache->put($root . '/source.phel', 'example', 'hash', '$value = 1;');
                echo $root;
                PHP,
                dirname(__DIR__, 4),
            ], env: [...getenv(), 'TMPDIR' => $root, 'TEST_TOKEN' => 'cleanup']);

            self::assertSame(0, $result->exitCode, $result->stderr);
            self::assertStringStartsWith($root . DIRECTORY_SEPARATOR, $result->stdout);
            self::assertDirectoryDoesNotExist($result->stdout);
        } finally {
            $this->removeDir($root);
        }
    }

    public function test_isolated_child_keeps_the_parent_worker_cache(): void
    {
        $root = sys_get_temp_dir() . '/phel-worker-cache-' . bin2hex(random_bytes(6));
        mkdir($root, 0o700, true);
        $cacheDir = $root . '/shared-cache';

        try {
            $result = Subprocess::run([
                PHP_BINARY,
                '-r',
                <<<'PHP'
                require $argv[1] . '/tests/bootstrap.php';
                $cacheDir = getenv('PHEL_CACHE_DIR');
                $cache = new \Phel\Build\Infrastructure\Cache\CompiledCodeCache($cacheDir);
                $cache->put($cacheDir . '/source.phel', 'example', 'hash', '$value = 1;');
                echo $cacheDir;
                PHP,
                dirname(__DIR__, 4),
            ], env: [...getenv(), 'TMPDIR' => $root . '/child', 'TEST_TOKEN' => 'child', 'PHEL_TEST_SHARED_CACHE_DIR' => $cacheDir]);

            self::assertSame(0, $result->exitCode, $result->stderr);
            self::assertSame($cacheDir, $result->stdout);
            self::assertFileExists($cacheDir . '/compiled-index.php');
            self::assertCount(1, glob($cacheDir . '/compiled/*.php'));
        } finally {
            $this->removeDir($root);
        }
    }
}
