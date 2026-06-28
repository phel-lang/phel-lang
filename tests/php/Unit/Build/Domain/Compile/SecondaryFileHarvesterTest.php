<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Compile;

use Phel\Build\Domain\Compile\CompiledSecondaryStore;
use Phel\Build\Domain\Compile\CompiledTargetPathResolver;
use Phel\Build\Domain\Compile\SecondaryFileHarvester;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Shared\CompiledSourceHash;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function file_get_contents;
use function is_dir;
use function md5;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class SecondaryFileHarvesterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-harvester-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_harvest_writes_secondary_compiled_at_optimization_level_2(): void
    {
        // FileEvaluator stores -O2 entries under md5(code . '|O2'); the harvester
        // must look them up with the same key or every secondary is dropped and
        // the build ships a broken artifact (#2449 / #2631).
        $target = $this->harvestSecondary(
            optimizationLevel: 2,
            storedHash: CompiledSourceHash::of('(in-ns phel\\core\\meta)', 2),
        );

        self::assertFileExists($target);
        self::assertStringContainsString('$compiledSecondary = true;', (string) file_get_contents($target));
    }

    public function test_harvest_writes_secondary_at_level_zero_with_plain_hash(): void
    {
        $target = $this->harvestSecondary(
            optimizationLevel: 0,
            storedHash: md5('(in-ns phel\\core\\meta)'),
        );

        self::assertFileExists($target);
        self::assertStringContainsString('$compiledSecondary = true;', (string) file_get_contents($target));
    }

    /**
     * Seeds the compiled-code cache with a secondary keyed by $storedHash, runs
     * the harvester at $optimizationLevel, and returns the expected output path.
     */
    private function harvestSecondary(int $optimizationLevel, string $storedHash): string
    {
        $sourceDir = $this->tempDir . '/src';
        $sourceFile = $sourceDir . '/phel/core/meta.phel';
        mkdir($sourceDir . '/phel/core', 0755, true);
        $sourceCode = '(in-ns phel\\core\\meta)';
        file_put_contents($sourceFile, $sourceCode);

        $namespace = 'phel\\core\\meta';
        $cache = new CompiledCodeCache($this->tempDir . '/cache');
        // CompiledCodeCache::put prepends the `<?php` opener itself.
        $cache->put($sourceFile, $namespace, $storedHash, '$compiledSecondary = true;');

        $harvester = new SecondaryFileHarvester(
            new CompiledTargetPathResolver($this->createStub(CompilerFacadeInterface::class)),
            new SystemFileIo(),
            new CompiledSecondaryStore(),
            $cache,
            $optimizationLevel,
        );

        $destDir = $this->tempDir . '/out';
        $secondary = new NamespaceInformation($sourceFile, $namespace, [], isPrimaryDefinition: false);
        $harvester->harvest($secondary, $destDir, [$sourceDir]);

        return $destDir . '/phel/core/meta.php';
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
