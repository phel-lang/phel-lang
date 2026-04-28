<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\CachedNamespaceExtractor;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Infrastructure\Cache\NullNamespaceCache;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CachedNamespaceExtractorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phel-cached-extractor-test-' . uniqid();
        mkdir($this->dir . '/split', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function test_primary_ns_file_is_returned_before_its_in_ns_siblings_regardless_of_scan_order(): void
    {
        $primaryPath = $this->dir . '/main.phel';
        $secondaryPath = $this->dir . '/split/part.phel';

        // Stub the inner extractor so we can simulate any scan order
        // independent of filesystem behaviour.
        $primaryInfo = new NamespaceInformation(
            $primaryPath,
            'split\\ns',
            [],
            isPrimaryDefinition: true,
        );
        $secondaryInfo = new NamespaceInformation(
            $secondaryPath,
            'split\\ns',
            ['split\\ns'],
            isPrimaryDefinition: false,
        );

        // Create real files so the iterator finds them.
        file_put_contents($primaryPath, '(ns split\\ns)');
        file_put_contents($secondaryPath, '(in-ns split\\ns)');

        $inner = $this->createMock(NamespaceExtractorInterface::class);
        $inner->method('getNamespaceFromFile')->willReturnCallback(
            static fn(string $path): NamespaceInformation => str_ends_with($path, 'main.phel') ? $primaryInfo : $secondaryInfo,
        );

        $extractor = new CachedNamespaceExtractor(
            $inner,
            new NullNamespaceCache(),
            new TopologicalNamespaceSorter(),
        );

        $infos = $extractor->getNamespacesFromDirectories([$this->dir]);
        $picked = array_values(array_filter(
            $infos,
            static fn(NamespaceInformation $i): bool => $i->getNamespace() === 'split\\ns',
        ));

        self::assertCount(2, $picked, 'Both primary and secondary files must be surfaced for build emission.');
        self::assertTrue(
            $picked[0]->isPrimaryDefinition(),
            'Primary `(ns ...)` file must come before any `(in-ns ...)` sibling.',
        );
        self::assertStringEndsWith('/main.phel', $picked[0]->getFile());
        self::assertFalse($picked[1]->isPrimaryDefinition());
        self::assertStringEndsWith('/split/part.phel', $picked[1]->getFile());
    }

    public function test_directory_scan_skips_files_that_fail_to_extract(): void
    {
        $goodPath = $this->dir . '/good.phel';
        $badPath = $this->dir . '/split/bad.phel';

        file_put_contents($goodPath, '(ns good\\ns)');
        file_put_contents($badPath, '(ns bad\\ns)');

        $goodInfo = new NamespaceInformation($goodPath, 'good\\ns', [], isPrimaryDefinition: true);

        $inner = $this->createMock(NamespaceExtractorInterface::class);
        $inner->method('getNamespaceFromFile')->willReturnCallback(
            static function (string $path) use ($goodInfo): NamespaceInformation {
                if (str_ends_with($path, 'good.phel')) {
                    return $goodInfo;
                }

                throw ExtractorException::cannotParseFile($path);
            },
        );

        $extractor = new CachedNamespaceExtractor(
            $inner,
            new NullNamespaceCache(),
            new TopologicalNamespaceSorter(),
        );

        $infos = $extractor->getNamespacesFromDirectories([$this->dir]);

        self::assertCount(1, $infos, 'Malformed file must be skipped, good file still returned.');
        self::assertSame('good\\ns', $infos[0]->getNamespace());
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
