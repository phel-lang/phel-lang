<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Extractor;

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
    protected function setUp(): void
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
