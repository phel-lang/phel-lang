<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Graph;

use Phel\Build\Application\FileSetDiffCalculator;
use Phel\Build\Application\IncrementalNamespaceExtractor;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Domain\Graph\DependencyGraph;
use Phel\Build\Domain\Graph\DependencyGraphCacheInterface;
use Phel\Build\Domain\Graph\FileSetSnapshot;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use stdClass;

/**
 * Integration tests for IncrementalNamespaceExtractor verifying caching behavior.
 */
final class IncrementalNamespaceExtractorIntegrationTest extends TestCase
{
    private string $testDir;

    private CountingGraphCache $graphCache;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/phel-incremental-test-' . uniqid();
        mkdir($this->testDir, 0755, true);
        $this->graphCache = new CountingGraphCache();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->testDir);
    }

    public function test_first_call_populates_cache(): void
    {
        $this->createPhelFile('a.phel');
        $this->createPhelFile('b.phel');

        $innerExtractor = $this->createCountingExtractor([
            new NamespaceInformation($this->testDir . '/a.phel', 'test\\a', []),
            new NamespaceInformation($this->testDir . '/b.phel', 'test\\b', ['test\\a']),
        ]);

        $extractor = new IncrementalNamespaceExtractor(
            $innerExtractor,
            $this->graphCache,
            new FileSetDiffCalculator(),
            new TopologicalNamespaceSorter(),
        );

        $result = $extractor->getNamespacesFromDirectories([$this->testDir]);

        self::assertCount(2, $result);
        self::assertSame('test\\a', $result[0]->getNamespace());
        self::assertSame('test\\b', $result[1]->getNamespace());

        // Cache should now be populated
        self::assertNotNull($this->graphCache->load());
        self::assertNotNull($this->graphCache->loadFileSet());
        self::assertSame(1, $this->graphCache->getSaveCount());
    }

    public function test_second_call_uses_cache_when_no_files_changed(): void
    {
        $this->createPhelFile('a.phel');

        $callCount = 0;
        $innerExtractor = new class($this->testDir, $callCount) implements NamespaceExtractorInterface {
            /** @var int */
            private $callCount;

            public function __construct(
                private readonly string $testDir,
                int &$callCount,
            ) {
                $this->callCount = &$callCount;
            }

            public function getNamespaceFromFile(string $path): NamespaceInformation
            {
                ++$this->callCount;
                return new NamespaceInformation($path, 'test\\a', []);
            }

            public function getNamespacesFromDirectories(array $directories): array
            {
                ++$this->callCount;
                return [
                    new NamespaceInformation($this->testDir . '/a.phel', 'test\\a', []),
                ];
            }
        };

        $extractor = new IncrementalNamespaceExtractor(
            $innerExtractor,
            $this->graphCache,
            new FileSetDiffCalculator(),
            new TopologicalNamespaceSorter(),
        );

        // First call - should delegate to inner extractor
        $result1 = $extractor->getNamespacesFromDirectories([$this->testDir]);
        $callsAfterFirst = $callCount;

        // Second call - should use cache
        $result2 = $extractor->getNamespacesFromDirectories([$this->testDir]);
        $callsAfterSecond = $callCount;

        // Results should be the same
        self::assertCount(1, $result1);
        self::assertCount(1, $result2);
        self::assertSame($result1[0]->getNamespace(), $result2[0]->getNamespace());

        // Inner extractor should NOT have been called for second request
        self::assertSame($callsAfterFirst, $callsAfterSecond);

        // Cache should have been saved only once
        self::assertSame(1, $this->graphCache->getSaveCount());
    }

    public function test_modified_file_triggers_cache_update(): void
    {
        $file = $this->createPhelFile('a.phel');

        $versionHolder = new stdClass();
        $versionHolder->value = 1;

        $innerExtractor = new readonly class($file, $versionHolder) implements NamespaceExtractorInterface {
            public function __construct(
                private string $file,
                private stdClass $versionHolder,
            ) {
            }

            public function getNamespaceFromFile(string $path): NamespaceInformation
            {
                return new NamespaceInformation($path, 'test\\v' . $this->versionHolder->value, []);
            }

            public function getNamespacesFromDirectories(array $directories): array
            {
                return [
                    new NamespaceInformation($this->file, 'test\\v' . $this->versionHolder->value, []),
                ];
            }
        };

        $extractor = new IncrementalNamespaceExtractor(
            $innerExtractor,
            $this->graphCache,
            new FileSetDiffCalculator(),
            new TopologicalNamespaceSorter(),
        );

        // First call
        $result1 = $extractor->getNamespacesFromDirectories([$this->testDir]);
        self::assertSame('test\\v1', $result1[0]->getNamespace());

        // Modify the file (update mtime) and version
        sleep(1);
        $versionHolder->value = 2;
        touch($file);

        // Second call - should detect modification
        $result2 = $extractor->getNamespacesFromDirectories([$this->testDir]);
        self::assertSame('test\\v2', $result2[0]->getNamespace());

        // Cache should have been saved twice
        self::assertSame(2, $this->graphCache->getSaveCount());
    }

    public function test_new_file_triggers_cache_update(): void
    {
        $fileA = $this->createPhelFile('a.phel');

        $filesHolder = new stdClass();
        $filesHolder->files = [$fileA];

        $innerExtractor = new readonly class($filesHolder) implements NamespaceExtractorInterface {
            public function __construct(
                private stdClass $filesHolder,
            ) {
            }

            public function getNamespaceFromFile(string $path): NamespaceInformation
            {
                $ns = basename($path, '.phel');

                return new NamespaceInformation($path, 'test\\' . $ns, []);
            }

            public function getNamespacesFromDirectories(array $directories): array
            {
                $result = [];
                foreach ($this->filesHolder->files as $file) {
                    $ns = basename((string) $file, '.phel');
                    $result[] = new NamespaceInformation($file, 'test\\' . $ns, []);
                }

                return $result;
            }
        };

        $extractor = new IncrementalNamespaceExtractor(
            $innerExtractor,
            $this->graphCache,
            new FileSetDiffCalculator(),
            new TopologicalNamespaceSorter(),
        );

        // First call with only file a
        $result1 = $extractor->getNamespacesFromDirectories([$this->testDir]);
        self::assertCount(1, $result1);
        self::assertSame('test\\a', $result1[0]->getNamespace());

        // Add new file
        $fileB = $this->createPhelFile('b.phel');
        $filesHolder->files[] = $fileB;

        // Second call - should detect new file
        $result2 = $extractor->getNamespacesFromDirectories([$this->testDir]);
        self::assertCount(2, $result2);

        $namespaces = array_map(static fn (NamespaceInformation $info): string => $info->getNamespace(), $result2);
        self::assertContains('test\\a', $namespaces);
        self::assertContains('test\\b', $namespaces);
    }

    public function test_get_namespace_from_file_delegates_to_inner(): void
    {
        $file = $this->createPhelFile('test.phel');
        $expectedInfo = new NamespaceInformation($file, 'test\\ns', []);

        $innerExtractor = $this->createCountingExtractor([$expectedInfo]);

        $extractor = new IncrementalNamespaceExtractor(
            $innerExtractor,
            $this->graphCache,
            new FileSetDiffCalculator(),
            new TopologicalNamespaceSorter(),
        );

        $result = $extractor->getNamespaceFromFile($file);

        self::assertSame('test\\ns', $result->getNamespace());
    }

    public function test_returns_correct_topological_order(): void
    {
        $this->createPhelFile('a.phel');
        $this->createPhelFile('b.phel');
        $this->createPhelFile('c.phel');

        // c depends on b, b depends on a
        $innerExtractor = $this->createCountingExtractor([
            new NamespaceInformation($this->testDir . '/a.phel', 'test\\a', []),
            new NamespaceInformation($this->testDir . '/b.phel', 'test\\b', ['test\\a']),
            new NamespaceInformation($this->testDir . '/c.phel', 'test\\c', ['test\\b']),
        ]);

        $extractor = new IncrementalNamespaceExtractor(
            $innerExtractor,
            $this->graphCache,
            new FileSetDiffCalculator(),
            new TopologicalNamespaceSorter(),
        );

        $result = $extractor->getNamespacesFromDirectories([$this->testDir]);

        self::assertCount(3, $result);
        // Should be in dependency order: a first (no deps), then b (depends on a), then c (depends on b)
        self::assertSame('test\\a', $result[0]->getNamespace());
        self::assertSame('test\\b', $result[1]->getNamespace());
        self::assertSame('test\\c', $result[2]->getNamespace());
    }

    private function createPhelFile(string $name): string
    {
        $path = $this->testDir . '/' . $name;
        file_put_contents($path, '(ns test)');
        return $path;
    }

    /**
     * @param list<NamespaceInformation> $infos
     */
    private function createCountingExtractor(array $infos): NamespaceExtractorInterface
    {
        return new class($infos) implements NamespaceExtractorInterface {
            public int $callCount = 0;

            /**
             * @param list<NamespaceInformation> $infos
             */
            public function __construct(private readonly array $infos)
            {
            }

            public function getNamespaceFromFile(string $path): NamespaceInformation
            {
                ++$this->callCount;
                foreach ($this->infos as $info) {
                    if ($info->getFile() === $path) {
                        return $info;
                    }
                }

                throw new RuntimeException('Unexpected file: ' . $path);
            }

            public function getNamespacesFromDirectories(array $directories): array
            {
                ++$this->callCount;
                return $this->infos;
            }
        };
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}

/**
 * Simple in-memory cache that counts save operations for testing.
 */
final class CountingGraphCache implements DependencyGraphCacheInterface
{
    private ?DependencyGraph $graph = null;

    private ?FileSetSnapshot $fileSet = null;

    private int $saveCount = 0;

    public function load(): ?DependencyGraph
    {
        return $this->graph;
    }

    public function loadFileSet(): ?FileSetSnapshot
    {
        return $this->fileSet;
    }

    public function save(DependencyGraph $graph, FileSetSnapshot $fileSet): void
    {
        $this->graph = $graph;
        $this->fileSet = $fileSet;
        ++$this->saveCount;
    }

    public function clear(): void
    {
        $this->graph = null;
        $this->fileSet = null;
    }

    public function getSaveCount(): int
    {
        return $this->saveCount;
    }
}
