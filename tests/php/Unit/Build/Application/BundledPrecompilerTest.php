<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\BundledPrecompiler;
use Phel\Build\Infrastructure\Cache\BundledCompiledCache;
use PHPUnit\Framework\TestCase;

final class BundledPrecompilerTest extends TestCase
{
    private string $root;

    private string $cacheDir;

    private string $bundleDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel-precompile-' . uniqid('', true);
        $this->cacheDir = $this->root . '/cache';
        $this->bundleDir = $this->root . '/bundle';
        mkdir($this->cacheDir . '/compiled', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function test_exports_bundled_namespaces_keyed_by_source_hash(): void
    {
        $compiledFile = $this->cacheDir . '/compiled/phel_core__abc.php';
        file_put_contents($compiledFile, "<?php\n// core compiled");
        file_put_contents(
            $this->cacheDir . '/compiled/phel.core.env.php',
            '<?php return ' . var_export(['refers' => []], true) . ';',
        );
        $this->writeIndex([
            '/src/phel/core.phel' => [
                'namespace' => 'phel.core',
                'source_hash' => 'corehash',
                'compiled_path' => $compiledFile,
            ],
        ]);

        $written = new BundledPrecompiler()->exportFromCache(
            $this->cacheDir,
            new BundledCompiledCache($this->bundleDir),
        );

        $bundle = new BundledCompiledCache($this->bundleDir);
        self::assertSame(1, $written);
        self::assertNotNull($bundle->compiledPath('corehash'));
        self::assertStringContainsString('// core compiled', (string) file_get_contents($bundle->compiledPath('corehash')));
        self::assertSame(['refers' => []], $bundle->environment('phel.core'));
    }

    public function test_skips_non_bundled_namespaces(): void
    {
        $compiledFile = $this->cacheDir . '/compiled/app_main__xyz.php';
        file_put_contents($compiledFile, "<?php\n// app");
        $this->writeIndex([
            '/src/app/main.phel' => [
                'namespace' => 'app.main',
                'source_hash' => 'apphash',
                'compiled_path' => $compiledFile,
            ],
        ]);

        $written = new BundledPrecompiler()->exportFromCache(
            $this->cacheDir,
            new BundledCompiledCache($this->bundleDir),
        );

        self::assertSame(0, $written);
        self::assertNull(new BundledCompiledCache($this->bundleDir)->compiledPath('apphash'));
    }

    public function test_resolves_relocated_compiled_path_by_basename(): void
    {
        // Index records a stale absolute path (as if built on another machine),
        // but the file exists under this cache dir's compiled/ by basename.
        $realFile = $this->cacheDir . '/compiled/phel_json__def.php';
        file_put_contents($realFile, "<?php\n// json");
        $this->writeIndex([
            '/src/phel/json.phel' => [
                'namespace' => 'phel.json',
                'source_hash' => 'jsonhash',
                'compiled_path' => '/build/workdir/cache/compiled/phel_json__def.php',
            ],
        ]);

        $written = new BundledPrecompiler()->exportFromCache(
            $this->cacheDir,
            new BundledCompiledCache($this->bundleDir),
        );

        self::assertSame(1, $written);
        self::assertNotNull(new BundledCompiledCache($this->bundleDir)->compiledPath('jsonhash'));
    }

    public function test_returns_zero_when_no_index(): void
    {
        $written = new BundledPrecompiler()->exportFromCache(
            $this->cacheDir,
            new BundledCompiledCache($this->bundleDir),
        );

        self::assertSame(0, $written);
    }

    /**
     * @param array<string, array{namespace: string, source_hash: string, compiled_path: string}> $entries
     */
    private function writeIndex(array $entries): void
    {
        file_put_contents(
            $this->cacheDir . '/compiled-index.php',
            '<?php return ' . var_export(['version' => 'test', 'entries' => $entries], true) . ';',
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*') ?: [];
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->removeDir($file);
            } else {
                @unlink($file);
            }
        }

        @rmdir($dir);
    }
}
