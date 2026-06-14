<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Phel\Build\Infrastructure\Cache\BundledCompiledCache;
use PHPUnit\Framework\TestCase;

final class BundledCompiledCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phel-bundled-' . uniqid('', true);
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
    }

    public function test_compiled_path_returns_null_when_missing(): void
    {
        $cache = new BundledCompiledCache($this->dir);

        self::assertNull($cache->compiledPath('unknown_hash'));
    }

    public function test_compiled_path_returns_file_when_present(): void
    {
        $cache = new BundledCompiledCache($this->dir);
        file_put_contents($cache->compiledTarget('abc123'), "<?php\n// code");

        $result = $cache->compiledPath('abc123');

        self::assertNotNull($result);
        self::assertStringContainsString('// code', (string) file_get_contents($result));
    }

    public function test_environment_returns_null_when_missing(): void
    {
        $cache = new BundledCompiledCache($this->dir);

        self::assertNull($cache->environment('phel\\core'));
    }

    public function test_environment_reads_back_stored_data(): void
    {
        $cache = new BundledCompiledCache($this->dir);
        $data = ['refers' => ['x' => ['ns' => 'phel\\core', 'name' => 'map']]];
        file_put_contents(
            $cache->environmentTarget('phel\\core'),
            '<?php return ' . var_export($data, true) . ';',
        );

        self::assertSame($data, $cache->environment('phel\\core'));
    }

    public function test_environment_munges_namespace_separators_in_path(): void
    {
        $cache = new BundledCompiledCache($this->dir);

        self::assertStringEndsWith('phel.core.env.php', $cache->environmentTarget('phel.core'));
        self::assertStringEndsWith('phel_core.env.php', $cache->environmentTarget('phel\\core'));
    }
}
