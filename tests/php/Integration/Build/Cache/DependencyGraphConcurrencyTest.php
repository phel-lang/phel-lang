<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Cache;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;
use function is_resource;

final class DependencyGraphConcurrencyTest extends TestCase
{
    use RemoveDirTrait;

    private string $root;

    protected function setUp(): void
    {
        $this->root = realpath(sys_get_temp_dir()) . '/phel-graph-workers-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function test_parallel_writers_preserve_every_edge_from_serial_writers(): void
    {
        for ($worker = 0; $worker < 2; ++$worker) {
            $result = Subprocess::run($this->command('serial', $worker, false));
            self::assertSame(0, $result->exitCode, $result->stderr);
        }

        $processes = [];
        try {
            for ($worker = 0; $worker < 2; ++$worker) {
                $process = proc_open($this->command('parallel', $worker, true), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                $processes[] = [$process, $pipes];
            }

            foreach ($processes as [, $pipes]) {
                stream_set_timeout($pipes[1], 10);
                self::assertSame("ready\n", fgets($pipes[1]));
            }

            foreach ($processes as [, $pipes]) {
                fwrite($pipes[0], "go\n");
                fclose($pipes[0]);
            }

            foreach ($processes as [$process, $pipes]) {
                stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $stderr);
            }
        } finally {
            foreach ($processes as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }

                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
        }

        self::assertSame($this->graph('serial'), $this->graph('parallel'));
    }

    public function test_parallel_phel_tests_persist_the_complete_fifty_namespace_graph(): void
    {
        mkdir($this->root . '/src');
        mkdir($this->root . '/tests');
        mkdir($this->root . '/vendor');
        file_put_contents($this->root . '/vendor/autoload.php', '<?php return require ' . var_export(dirname(__DIR__, 5) . '/vendor/autoload.php', true) . ';');
        file_put_contents($this->root . '/phel-config.php', <<<'PHP'
<?php return new \Phel\Config\PhelConfig()->withSrcDirs(['src'])->withTestDirs(['tests'])->withVendorDir('')->withTempDir(__DIR__ . '/tmp');
PHP);
        for ($worker = 0; $worker < 2; ++$worker) {
            for ($index = 0; $index < 25; ++$index) {
                $require = $index === 0 ? '' : ' (:require app.group' . $worker . '.ns' . ($index - 1) . ')';
                file_put_contents($this->root . '/src/group' . $worker . '_' . $index . '.phel', '(ns app.group' . $worker . '.ns' . $index . $require . ')');
            }

            file_put_contents(
                $this->root . '/tests/worker' . $worker . '.phel',
                '(ns phel-test.worker' . $worker . ' (:require phel.test :refer [deftest is]))'
                . '(deftest loads-project (.evalFile (Phel.Build.BuildFacade.) "' . $this->root . '/src/group' . $worker . '_24.phel") (is true))',
            );
        }

        foreach (['serial-cli' => [], 'parallel-cli' => ['--parallel=2']] as $cache => $options) {
            $env = getenv();
            $env['PHEL_CACHE_DIR'] = $this->root . '/' . $cache;
            $env['PHEL_NO_OPCACHE_REEXEC'] = '1';
            $result = Subprocess::run([PHP_BINARY, dirname(__DIR__, 5) . '/bin/phel', 'test', ...$options], $this->root, env: $env);
            self::assertSame(0, $result->exitCode, $result->stdout . $result->stderr);
        }

        $serial = $this->graph('serial-cli');
        self::assertCount(50, array_filter(array_keys($serial), fn(string $key): bool => str_starts_with($key, 'file:' . $this->root . '/src/')));
        self::assertSame($serial, $this->graph('parallel-cli'));
    }

    public function test_cold_fifty_namespace_compile_commits_the_graph_once(): void
    {
        mkdir($this->root . '/src');
        mkdir($this->root . '/vendor');
        file_put_contents($this->root . '/vendor/autoload.php', '<?php return require ' . var_export(dirname(__DIR__, 5) . '/vendor/autoload.php', true) . ';');
        file_put_contents($this->root . '/phel-config.php', <<<'PHP'
<?php return new \Phel\Config\PhelConfig()->withSrcDirs(['src'])->withVendorDir('')->withTempDir(__DIR__ . '/tmp');
PHP);
        for ($index = 0; $index < 50; ++$index) {
            $require = $index === 0 ? '' : ' (:require app.ns' . ($index - 1) . ')';
            file_put_contents($this->root . '/src/ns' . $index . '.phel', '(ns app.ns' . $index . $require . ')' . ($index === 49 ? '(println 42)' : ''));
        }

        $code = <<<'PHP'
require $argv[1] . '/vendor/autoload.php';
class_exists(\PhelTest\Integration\Build\Cache\GraphWriteStream::class);
stream_wrapper_unregister('file');
stream_wrapper_register('file', \PhelTest\Integration\Build\Cache\GraphWriteStream::class);
$metrics = $argv[2] . '/writes.json';
register_shutdown_function(static function () use ($metrics): void {
    $before = \PhelTest\Integration\Build\Cache\GraphWriteStream::$graphWrites;
    register_shutdown_function(static function () use ($metrics, $before): void {
        file_put_contents($metrics, json_encode([$before, \PhelTest\Integration\Build\Cache\GraphWriteStream::$graphWrites]));
    });
});
$_SERVER['argv'] = [$argv[1] . '/bin/phel', 'run', 'src/ns49.phel'];
require $argv[1] . '/bin/phel';
PHP;
        $env = getenv();
        $env['PHEL_CACHE_DIR'] = $this->root . '/cold-cli';
        $env['PHEL_NO_OPCACHE_REEXEC'] = '1';
        $result = Subprocess::run([PHP_BINARY, '-r', $code, dirname(__DIR__, 5), $this->root], $this->root, env: $env);
        self::assertSame(0, $result->exitCode, $result->stdout . $result->stderr);
        self::assertStringContainsString('42', $result->stdout);
        self::assertSame([0, 1], json_decode(file_get_contents($this->root . '/writes.json'), true));
        self::assertCount(50, array_filter(array_keys($this->graph('cold-cli')), fn(string $key): bool => str_starts_with($key, 'file:' . $this->root . '/src/')));
    }

    /**
     * @return list<string>
     */
    private function command(string $cache, int $worker, bool $barrier): array
    {
        $code = <<<'PHP'
require $argv[1];
$tracker = new \Phel\Build\Infrastructure\Cache\DependencyTracker($argv[2]);
if ($argv[4] === 'wait') {
    echo "ready\n";
    fflush(STDOUT);
    fgets(STDIN);
}
for ($index = 0; $index < 5; ++$index) {
    $tracker->registerDependencies('/sources/worker' . $argv[3] . '/ns' . $index . '.phel', 'worker' . $argv[3] . '.ns' . $index, ['phel.core', 'shared.macros']);
}
PHP;

        return [PHP_BINARY, '-r', $code, dirname(__DIR__, 5) . '/vendor/autoload.php', $this->root . '/' . $cache, (string) $worker, $barrier ? 'wait' : 'now'];
    }

    /**
     * @return array<string, list<string>>
     */
    private function graph(string $cache): array
    {
        $graph = include $this->root . '/' . $cache . '/dependency-graph/.gacela-scoped-cache-graph.php';
        foreach ($graph as &$parents) {
            sort($parents);
        }

        ksort($graph);

        return $graph;
    }
}
