<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Extractor;

use Phel\Build\Extractor\ExtractorException;
use Phel\Build\Extractor\NamespaceExtractor;
use Phel\Build\Extractor\NamespaceInformation;
use Phel\Build\Extractor\TopologicalNamespaceSorter;
use Phel\Compiler\CompilerFacade;
use PHPUnit\Framework\TestCase;

final class NamespaceExtractorTest extends TestCase
{
    public function test_get_namespace_from_file(): void
    {
        $fileContent = '(ns get\\ns\\from\\file)';
        $result = $this->extractNamespace($fileContent);

        $this->assertEquals('get\\ns\\from\\file', $result->getNamespace());
        $this->assertEquals(['phel\core'], $result->getDependencies());
    }

    public function test_get_namespace_from_file_with_dependecies(): void
    {
        $fileContent = '(ns get\\ns\\from\\file (:require phel\html))';
        $result = $this->extractNamespace($fileContent);

        $this->assertEquals('get\\ns\\from\\file', $result->getNamespace());
        $this->assertEquals(['phel\core', 'phel\html'], $result->getDependencies());
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

    private function extractNamespace($code): NamespaceInformation
    {
        $filePath = tempnam(sys_get_temp_dir(), self::class);
        file_put_contents($filePath, $code);

        $nsExtractor = new NamespaceExtractor(
            new CompilerFacade(),
            new TopologicalNamespaceSorter()
        );

        return $nsExtractor->getNamespaceFromFile($filePath);
    }
}
