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
                echo json_encode([$cacheDir, getenv('GACELA_CACHE_DIR'), getenv('TMPDIR')]);
                PHP,
                dirname(__DIR__, 4),
            ], env: [...getenv(), 'TMPDIR' => $root . '/child', 'TEST_TOKEN' => 'child', 'PHEL_TEST_SHARED_CACHE_DIR' => $cacheDir, 'GACELA_CACHE_DIR' => $root . '/parent-config']);

            self::assertSame(0, $result->exitCode, $result->stderr);
            [$actualCache, $configCache, $childTemp] = json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($cacheDir, $actualCache);
            self::assertSame($childTemp . DIRECTORY_SEPARATOR . 'phel-phpunit-gacela-cache', $configCache);
            self::assertFileExists($cacheDir . '/compiled-index.php');
            self::assertCount(1, glob($cacheDir . '/compiled/*.php'));
        } finally {
            $this->removeDir($root);
        }
    }

    public function test_per_test_reset_removes_current_merged_configs_and_keeps_service_caches(): void
    {
        $result = Subprocess::run([
            PHP_BINARY,
            '-r',
            <<<'PHP'
            require $argv[1] . '/tests/bootstrap.php';
            $isolation = new \PhelTest\Support\PerTestGacelaCache();
            $isolation->isolate();
            $dir = getenv('GACELA_CACHE_DIR');
            $cache = new \Gacela\Framework\Config\MergedConfigCache($dir, appRootDir: $argv[1]);
            $cache->writeTrusted(['temp-dir' => 'previous-test']);
            file_put_contents($dir . '/gacela-custom-services.php', '<?php return [];');
            $isolation->isolate();
            echo json_encode([$cache->exists(), is_file($dir . '/gacela-custom-services.php')]);
            PHP,
            dirname(__DIR__, 4),
        ], env: [...getenv(), 'TEST_TOKEN' => 'config-reset']);

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertSame([false, true], json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR));
    }
}
