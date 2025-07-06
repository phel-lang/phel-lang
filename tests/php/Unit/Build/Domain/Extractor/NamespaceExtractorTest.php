<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Extractor;

use Gacela\Framework\Gacela;
use Phel\Build\Application\NamespaceExtractor;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Compiler\CompilerFacade;
use PHPUnit\Framework\TestCase;

final class NamespaceExtractorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_get_namespace_from_file(): void
    {
        $fileContent = '(ns get\\ns\\from\\file)';
        $result = $this->extractNamespace($fileContent);

        $this->assertSame('get\\ns\\from\\file', $result->getNamespace());
        $this->assertSame(['phel\core'], $result->getDependencies());
    }

    public function test_get_namespace_from_file_with_dependencies(): void
    {
        $fileContent = '(ns get\\ns\\from\\file (:require phel\html))';
        $result = $this->extractNamespace($fileContent);

        $this->assertSame('get\\ns\\from\\file', $result->getNamespace());
        $this->assertSame(['phel\core', 'phel\html'], $result->getDependencies());
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

    public function test_get_namespaces_from_directories_phar_path(): void
    {
        $extractor = $this->createExtractor();

        $result = $extractor->getNamespacesFromDirectories(['phar://phel.phar/src']);

        self::assertSame([], $result);
    }

    public function test_get_namespaces_from_directories_non_existing(): void
    {
        $extractor = $this->createExtractor();

        $result = $extractor->getNamespacesFromDirectories(['/does/not/exist']);

        self::assertSame([], $result);
    }

    public function test_get_namespaces_from_directories_collects_namespaces(): void
    {
        $dir = sys_get_temp_dir() . '/' . uniqid('phel', true);
        mkdir($dir);
        file_put_contents($dir . '/a.phel', '(ns my\\ns1 (:require my\\ns2))');
        file_put_contents($dir . '/b.phel', '(ns my\\ns2)');

        $extractor = $this->createExtractor();

        $namespaces = $extractor->getNamespacesFromDirectories([$dir]);
        $names = array_map(static fn (NamespaceInformation $n): string => $n->getNamespace(), $namespaces);

        self::assertSame(['my\\ns2', 'my\\ns1'], $names);

        unlink($dir . '/a.phel');
        unlink($dir . '/b.phel');
        rmdir($dir);
    }

    private function extractNamespace(string $code): NamespaceInformation
    {
        $filePath = tempnam(sys_get_temp_dir(), self::class);
        file_put_contents($filePath, $code);

        return $this->createExtractor()
            ->getNamespaceFromFile($filePath);
    }

    private function createExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            new CompilerFacade(),
            new TopologicalNamespaceSorter(),
            new SystemFileIo(),
        );
    }
}
