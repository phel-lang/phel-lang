<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\DependenciesForNamespace;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Lang\Registry;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

use function array_map;

final class DependenciesForNamespaceTest extends TestCase
{
    protected function tearDown(): void
    {
        Registry::getInstance()->clear();
    }

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

    public function test_throws_when_resolved_namespace_requires_a_missing_namespace(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('app.phel', 'app\\main', ['phel.core', 'some.missing.ns']),
            ]);

        $deps = new DependenciesForNamespace($extractor);

        $this->expectException(ExtractorException::class);
        $this->expectExceptionMessage("Cannot find namespace 'some.missing.ns' required by 'app\\main'");

        $deps->getDependenciesForNamespace(['/src'], ['app\\main']);
    }

    public function test_does_not_throw_for_unresolved_seed_with_no_requiring_namespace(): void
    {
        // A seed that resolves to nothing is the caller's concern (the REPL
        // checks the empty result itself); only an unresolved *dependency* of
        // a resolved namespace is a broken require.
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
            ]);

        $deps = new DependenciesForNamespace($extractor);

        self::assertSame([], $deps->getDependenciesForNamespace(['/src'], ['some.missing.ns']));
    }

    public function test_does_not_throw_when_clojure_dependency_maps_to_a_bundled_phel_namespace(): void
    {
        // The extractor records `clojure.string` raw at cold scan time (the
        // `phel.string` target is not registered yet), but it resolves through
        // the same `clojure.* -> phel.*` remap the analyzer/runner use.
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('string.phel', 'phel.string', ['phel.core']),
                new NamespaceInformation('app.phel', 'app\\main', ['phel.core', 'clojure.string']),
            ]);

        $deps = new DependenciesForNamespace($extractor);

        $result = $deps->getDependenciesForNamespace(['/src'], ['app\\main']);

        self::assertSame(
            ['phel.core', 'phel.string', 'app\\main'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $result),
        );
    }

    public function test_does_not_throw_when_required_namespace_is_already_registered(): void
    {
        // Namespaces already loaded into the runtime registry (e.g. a lazily
        // loaded bundled module) have no source file in the scan but are still
        // resolvable, so requiring them must not error.
        Registry::getInstance()->registerNamespace('already.loaded');

        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('app.phel', 'app\\main', ['phel.core', 'already.loaded']),
            ]);

        $deps = new DependenciesForNamespace($extractor);

        $result = $deps->getDependenciesForNamespace(['/src'], ['app\\main']);

        self::assertSame(
            ['phel.core', 'app\\main'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $result),
        );
    }
}
