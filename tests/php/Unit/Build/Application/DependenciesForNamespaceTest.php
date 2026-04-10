<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\DependenciesForNamespace;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use PHPUnit\Framework\TestCase;

final class DependenciesForNamespaceTest extends TestCase
{
    public function test_returns_empty_for_unknown_namespace(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel\\core', []),
            ]);

        $deps = new DependenciesForNamespace($extractor);
        $result = $deps->getDependenciesForNamespace(['/src'], ['foo']);

        self::assertSame([], $result);
    }

    public function test_returns_dependencies_for_existing_namespace(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel\\core', []),
                new NamespaceInformation('app.phel', 'app\\main', ['phel\\core']),
            ]);

        $deps = new DependenciesForNamespace($extractor);
        $result = $deps->getDependenciesForNamespace(['/src'], ['app\\main']);

        self::assertCount(2, $result);
        self::assertSame('phel\\core', $result[0]->getNamespace());
        self::assertSame('app\\main', $result[1]->getNamespace());
    }
}
