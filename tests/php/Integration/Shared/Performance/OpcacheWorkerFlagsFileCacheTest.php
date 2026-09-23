<?php

declare(strict_types=1);

namespace PhelTest\Integration\Shared\Performance;

use Phel\Shared\Performance\OpcacheWorkerFlags;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function array_map;
use function bin2hex;
use function escapeshellarg;
use function exec;
use function extension_loaded;
use function file_put_contents;
use function implode;
use function iterator_to_array;
use function mkdir;
use function random_bytes;
use function realpath;
use function str_ends_with;
use function str_starts_with;
use function sys_get_temp_dir;
use function time;
use function touch;

final class OpcacheWorkerFlagsFileCacheTest extends TestCase
{
    use RemoveDirTrait;

    private string $dir;

    protected function setUp(): void
    {
        if (!extension_loaded('Zend OPcache')) {
            self::markTestSkipped('Zend OPcache is not loaded.');
        }

        $this->dir = realpath(sys_get_temp_dir()) . '/phel-worker-flags-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/cache', 0o777, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && str_starts_with($this->dir, realpath(sys_get_temp_dir()) . '/phel-worker-flags-')) {
            $this->removeDir($this->dir);
        }
    }

    /**
     * A php.ini that turns the JIT on (setup-php does, for every runner) must
     * not reach the workers: with the JIT on, OPcache writes no file cache,
     * and the pool shares nothing.
     */
    public function test_a_worker_writes_the_file_cache_even_when_the_ini_enables_the_jit(): void
    {
        $script = $this->dir . '/script.php';
        file_put_contents($script, "<?php\nfunction f(int \$x): int { return \$x + 1; }\necho f(1);\n");
        // OPcache skips a file changed within `opcache.file_update_protection`.
        touch($script, time() - 60);

        $inherited = ['-d', 'opcache.jit=1235', '-d', 'opcache.jit_buffer_size=64M'];
        $flags = OpcacheWorkerFlags::forFileCache(true, $this->dir . '/cache');
        $cmd = [PHP_BINARY, ...$inherited, ...$flags, $script];

        exec(implode(' ', array_map(escapeshellarg(...), $cmd)) . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertNotSame([], $this->binFiles(), 'the worker wrote nothing to the OPcache file cache');
    }

    /**
     * @return list<string>
     */
    private function binFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir . '/cache', RecursiveDirectoryIterator::SKIP_DOTS));
        foreach (iterator_to_array($iterator) as $file) {
            if (str_ends_with($file->getPathname(), '.bin')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
