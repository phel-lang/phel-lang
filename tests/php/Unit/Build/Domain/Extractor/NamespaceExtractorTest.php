<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Extractor;

use Phel\Build\Application\NamespaceExtractor;
use Phel\Build\Domain\Extractor\ExcludedScanPaths;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Domain\IO\FileContentsIoInterface;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Phel;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function basename;
use function sprintf;

final class NamespaceExtractorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
    }

    public function test_get_namespace_from_file(): void
    {
        $fileContent = '(ns get\\ns\\from\\file)';
        $result = $this->extractNamespace($fileContent);

        $this->assertSame('get.ns.from.file', $result->getNamespace());
        $this->assertSame(['phel.core'], $result->getDependencies());
    }

    public function test_get_namespace_from_file_with_dependencies(): void
    {
        $fileContent = '(ns get\\ns\\from\\file (:require phel\html))';
        $result = $this->extractNamespace($fileContent);

        $this->assertSame('get.ns.from.file', $result->getNamespace());
        $this->assertSame(['phel.core', 'phel.html'], $result->getDependencies());
    }

    public function test_get_namespace_from_file_with_in_ns(): void
    {
        $fileContent = '(in-ns get\\ns\\from\\file)';
        $result = $this->extractNamespace($fileContent);

        $this->assertSame('get.ns.from.file', $result->getNamespace());
        $this->assertSame(['phel.core'], $result->getDependencies());
    }

    public function test_get_namespace_from_file_not_parsable(): void
    {
        $this->expectException(ExtractorException::class);
        $fileContent = '(';
        $this->extractNamespace($fileContent);
    }

    public function test_get_namespace_from_file_empty_file(): void
    {
        $this->expectException(ExtractorException::class);
        $fileContent = '';
        $this->extractNamespace($fileContent);
    }

    public function test_get_namespace_from_file_no_namespace_node(): void
    {
        $this->expectException(ExtractorException::class);
        $fileContent = '(php/+ 1 1)';
        $this->extractNamespace($fileContent);
    }

    public function test_get_namespace_from_file_unlexable_content_throws(): void
    {
        $this->expectException(ExtractorException::class);
        $fileContent = '## markdown-style comments are not valid phel';
        $this->extractNamespace($fileContent);
    }

    public function test_parse_failure_keeps_the_underlying_reason(): void
    {
        try {
            $this->extractNamespace('## markdown-style comments are not valid phel');
            self::fail('Expected an ExtractorException.');
        } catch (ExtractorException $extractorException) {
            $previous = $extractorException->getPrevious();

            self::assertInstanceOf(LexerValueException::class, $previous);
            self::assertStringContainsString(
                $previous->getMessage(),
                $extractorException->getMessage(),
                'The lexer/parser reason must survive into the ExtractorException message.',
            );
        }
    }

    public function test_scan_skips_unparseable_files(): void
    {
        $dir = sys_get_temp_dir() . '/phel-extractor-test-' . uniqid();
        mkdir($dir, 0777, true);

        $goodPath = $dir . '/good.phel';
        $badPath = $dir . '/bad.phel';

        file_put_contents($goodPath, '(ns good\\ns)');
        file_put_contents($badPath, '## not valid phel, lexer will throw');

        $nsExtractor = new NamespaceExtractor(
            new CompilerFacade(),
            new TopologicalNamespaceSorter(),
            new SystemFileIo(),
        );

        $infos = $nsExtractor->getNamespacesFromDirectories([$dir]);

        self::assertCount(1, $infos, 'Malformed file must be skipped, good file still returned.');
        self::assertSame('good.ns', $infos[0]->getNamespace());

        unlink($goodPath);
        unlink($badPath);
        rmdir($dir);
    }

    public function test_primary_ns_file_comes_before_its_in_ns_siblings(): void
    {
        $dir = sys_get_temp_dir() . '/phel-extractor-test-' . uniqid();
        mkdir($dir . '/split', 0777, true);

        $primaryPath = $dir . '/main.phel';
        $secondaryPath = $dir . '/split/part.phel';

        // Intentionally write the secondary first so directory-scan order
        // does not accidentally put the primary first on its own.
        file_put_contents($secondaryPath, '(in-ns split\\ns)');
        file_put_contents($primaryPath, '(ns split\\ns)');

        $nsExtractor = new NamespaceExtractor(
            new CompilerFacade(),
            new TopologicalNamespaceSorter(),
            new SystemFileIo(),
        );

        $infos = $nsExtractor->getNamespacesFromDirectories([$dir]);

        $matches = array_values(array_filter(
            $infos,
            static fn(NamespaceInformation $i): bool => $i->getNamespace() === 'split.ns',
        ));

        self::assertCount(2, $matches, 'Both primary and secondary files must be surfaced for build emission.');
        self::assertTrue(
            $matches[0]->isPrimaryDefinition(),
            'Primary `(ns ...)` file must come before any `(in-ns ...)` sibling.',
        );
        self::assertStringEndsWith('/main.phel', $matches[0]->getFile());
        self::assertFalse($matches[1]->isPrimaryDefinition());
        self::assertStringEndsWith('/split/part.phel', $matches[1]->getFile());

        unlink($secondaryPath);
        unlink($primaryPath);
        rmdir($dir . '/split');
        rmdir($dir);
    }

    public function test_scan_skips_configured_output_subdirectory(): void
    {
        $dir = sys_get_temp_dir() . '/phel-extractor-test-' . uniqid();
        mkdir($dir . '/out/phel', 0777, true);

        $sourcePath = $dir . '/src.phel';
        $outputCopyPath = $dir . '/out/phel/src.phel';

        file_put_contents($sourcePath, '(ns app\\main)');
        file_put_contents($outputCopyPath, '(ns app\\main)');

        $nsExtractor = new NamespaceExtractor(
            new CompilerFacade(),
            new TopologicalNamespaceSorter(),
            new SystemFileIo(),
            new ExcludedScanPaths(destDirBasename: 'out'),
        );

        $infos = $nsExtractor->getNamespacesFromDirectories([$dir]);

        self::assertCount(1, $infos, 'Output subtree must not be walked.');
        self::assertStringEndsWith('/src.phel', $infos[0]->getFile());

        unlink($sourcePath);
        unlink($outputCopyPath);
        rmdir($dir . '/out/phel');
        rmdir($dir . '/out');
        rmdir($dir);
    }

    public function test_scan_skips_absolute_excluded_directory(): void
    {
        $dir = sys_get_temp_dir() . '/phel-extractor-test-' . uniqid();
        mkdir($dir . '/vendor-build', 0777, true);

        $sourcePath = $dir . '/src.phel';
        $excludedPath = $dir . '/vendor-build/copy.phel';

        file_put_contents($sourcePath, '(ns app\\main)');
        file_put_contents($excludedPath, '(ns app\\main)');

        $nsExtractor = new NamespaceExtractor(
            new CompilerFacade(),
            new TopologicalNamespaceSorter(),
            new SystemFileIo(),
            new ExcludedScanPaths(excludedDirectories: [$dir . '/vendor-build']),
        );

        $infos = $nsExtractor->getNamespacesFromDirectories([$dir]);

        self::assertCount(1, $infos, 'Excluded directory subtree must not be walked.');
        self::assertStringEndsWith('/src.phel', $infos[0]->getFile());

        unlink($sourcePath);
        unlink($excludedPath);
        rmdir($dir . '/vendor-build');
        rmdir($dir);
    }

    public function test_unreadable_file_raises_an_extractor_exception(): void
    {
        // Asking for one specific file must still fail loudly - only the
        // directory scan is allowed to skip.
        $this->expectException(ExtractorException::class);
        $this->expectExceptionMessageMatches('~Cannot read file: .*does-not-exist\.phel~');

        $this->newExtractor()->getNamespaceFromFile(
            sys_get_temp_dir() . '/phel-does-not-exist.phel',
        );
    }

    public function test_directory_scan_skips_a_file_that_vanished_mid_scan(): void
    {
        // A file listed by the scan can be deleted before it is read - another
        // process writing a temp `.phel` and removing it is enough. The scan
        // must skip it and still return the namespaces it could read.
        $dir = sys_get_temp_dir() . '/phel-vanishing-' . bin2hex(random_bytes(8));
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/kept.phel', '(ns vanishing\\kept)');

        $vanishing = $dir . '/vanishing.phel';
        file_put_contents($vanishing, '(ns vanishing\\gone)');

        $extractor = new NamespaceExtractor(
            new CompilerFacade(),
            new TopologicalNamespaceSorter(),
            // Matched on basename: the scan realpaths the directory first, and
            // on macOS sys_get_temp_dir() resolves /var -> /private/var, so the
            // path the scan hands us is not the string we wrote.
            new readonly class(basename($vanishing)) implements FileContentsIoInterface {
                public function __construct(private string $vanishingName) {}

                public function getContents(string $filename): string
                {
                    if (basename($filename) === $this->vanishingName) {
                        throw new RuntimeException(sprintf('Unable to read file "%s".', $filename));
                    }

                    return new SystemFileIo()->getContents($filename);
                }

                public function putContents(string $filename, string $content): void
                {
                    new SystemFileIo()->putContents($filename, $content);
                }

                public function removeFile(string $filename): void
                {
                    new SystemFileIo()->removeFile($filename);
                }
            },
        );

        try {
            $infos = $extractor->getNamespacesFromDirectories([$dir]);
        } finally {
            @unlink($dir . '/kept.phel');
            @unlink($vanishing);
            @rmdir($dir);
        }

        $namespaces = array_map(
            static fn(NamespaceInformation $i): string => $i->getNamespace(),
            $infos,
        );

        self::assertSame(['vanishing.kept'], $namespaces);
    }

    private function newExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            new CompilerFacade(),
            new TopologicalNamespaceSorter(),
            new SystemFileIo(),
        );
    }

    private function extractNamespace(string $code): NamespaceInformation
    {
        $filePath = tempnam(sys_get_temp_dir(), self::class);
        file_put_contents($filePath, $code);

        $nsExtractor = new NamespaceExtractor(
            new CompilerFacade(),
            new TopologicalNamespaceSorter(),
            new SystemFileIo(),
        );

        return $nsExtractor->getNamespaceFromFile($filePath);
    }
}
