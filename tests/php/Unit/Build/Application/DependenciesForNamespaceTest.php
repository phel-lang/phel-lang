<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\DependenciesForNamespace;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

use function array_map;

final class DependenciesForNamespaceTest extends TestCase
{
    public function test_returns_empty_for_unknown_namespace(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
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
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('app.phel', 'app\\main', ['phel.core']),
            ]);

        $deps = new DependenciesForNamespace($extractor);
        $result = $deps->getDependenciesForNamespace(['/src'], ['app\\main']);

        self::assertCount(2, $result);
        self::assertSame('phel.core', $result[0]->getNamespace());
        self::assertSame('app\\main', $result[1]->getNamespace());
    }

    public function test_memoizes_per_dirs_and_seeds_within_process(): void
    {
        $extractor = $this->createMock(NamespaceExtractorInterface::class);
        // Two distinct (dirs, seeds) combinations -> exactly two extractions;
        // repeats of either combination must be served from the memo.
        $extractor->expects(self::exactly(2))
            ->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('app.phel', 'app\\main', ['phel.core']),
            ]);

        $deps = new DependenciesForNamespace($extractor);

        $first = $deps->getDependenciesForNamespace(['/src'], ['app\\main']);
        $repeat = $deps->getDependenciesForNamespace(['/src'], ['app\\main']);
        $other = $deps->getDependenciesForNamespace(['/other'], ['app\\main']);

        self::assertSame(
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $first),
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $repeat),
            'Repeated (dirs, seeds) must return the memoized result.',
        );
        self::assertCount(2, $other);
    }
}
