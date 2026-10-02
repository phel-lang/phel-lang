<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel;
use Phel\Build\Application\BundledNamespaceIndex;
use Phel\Build\Application\DependenciesForNamespace;
use Phel\Build\Application\MissingRequireReporter;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Compiler\CompilerFacade;
use Phel\Lang\Registry;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Facade\CommandFacadeInterface;
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

        $deps = $this->deps($extractor);
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

        $deps = $this->deps($extractor);
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

        $deps = $this->deps($extractor);

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

        $deps = $this->deps($extractor);

        $this->expectException(ExtractorException::class);
        // The requiring namespace is reported in canonical dot form, whichever
        // separator its `(ns ...)` used.
        $this->expectExceptionMessage("Cannot find namespace 'some.missing.ns' required by 'app.main'");

        $deps->getDependenciesForNamespace(['/src'], ['app\\main']);
    }

    public function test_a_missing_require_points_at_the_namespace_in_the_requiring_file(): void
    {
        Phel::bootstrap(__DIR__);
        $file = (string) tempnam(sys_get_temp_dir(), 'phel-missing-ns');
        file_put_contents($file, "(ns app.util\n  (:require [app.helpers :as h]))\n");

        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([new NamespaceInformation($file, 'app.util', ['app.helpers'])]);

        try {
            $this->deps($extractor)->getDependenciesForNamespace(['/src'], ['app.util']);
            self::fail('Expected a CompilerException.');
        } catch (CompilerException $compilerException) {
            $located = $compilerException->getNestedException();
            self::assertSame(ErrorCode::MISSING_NAMESPACE, $located->getErrorCode());
            self::assertStringStartsWith("Cannot find namespace 'app.helpers' required by 'app.util'", $located->getMessage());
            self::assertSame($file, $located->getStartLocation()?->getFile());
            self::assertSame(2, $located->getStartLocation()?->getLine());
            self::assertSame(13, $located->getStartLocation()?->getColumn());
            self::assertSame('searched: /src', $located->getRelatedLocationNote());
        } finally {
            unlink($file);
        }
    }

    public function test_clojure_set_require_resolves_to_phel_core(): void
    {
        // The clojure-test-suite requires `clojure.set`; Phel ships no
        // `phel.set`, its functions live in phel.core.
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('nnext.cljc', 'clojure.core-test.nnext', ['clojure.set']),
            ]);

        $result = $this->deps($extractor)->getDependenciesForNamespace(['/src'], ['clojure.core-test.nnext']);

        self::assertSame(
            ['phel.core', 'clojure.core-test.nnext'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $result),
        );
    }

    public function test_a_clojure_seed_resolves_to_its_phel_target(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('string.phel', 'phel.string', ['phel.core']),
            ]);

        $result = $this->deps($extractor)->getDependenciesForNamespace(['/src'], ['clojure.string']);

        self::assertSame(
            ['phel.core', 'phel.string'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $result),
        );
    }

    public function test_throws_with_a_suggestion_for_a_misspelled_phel_require(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('app.phel', 'app.main', ['phel.strng']),
            ]);

        $this->expectException(ExtractorException::class);
        $this->expectExceptionMessage("Cannot find namespace 'phel.strng' required by 'app.main'. Did you mean 'phel.string'?");

        $this->deps($extractor, ['phel.core', 'phel.string'])->getDependenciesForNamespace(['/src'], ['app.main']);
    }

    public function test_throws_for_a_phel_require_that_nothing_ships(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('app.phel', 'app.main', ['phel.nonexistent']),
            ]);

        $this->expectException(ExtractorException::class);
        $this->expectExceptionMessage("Cannot find namespace 'phel.nonexistent' required by 'app.main'.");

        $this->deps($extractor)->getDependenciesForNamespace(['/src'], ['app.main']);
    }

    public function test_throws_for_a_clojure_require_with_no_phel_target(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('app.phel', 'app.main', ['clojure.nothere']),
            ]);

        $this->expectException(ExtractorException::class);
        $this->expectExceptionMessage("Cannot find namespace 'clojure.nothere' required by 'app.main'.");

        $this->deps($extractor)->getDependenciesForNamespace(['/src'], ['app.main']);
    }

    public function test_does_not_throw_for_bundled_phel_require_absent_from_scan_index(): void
    {
        // A vendored build's scan can lack the stdlib; a `phel.*` namespace
        // shipped on the configured source or vendor dirs still resolves.
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('app.phel', 'app\\main', ['phel.core', 'phel.json']),
            ]);

        $deps = $this->deps($extractor);

        $result = $deps->getDependenciesForNamespace(['/src'], ['app\\main']);

        self::assertSame(
            ['phel.core', 'app\\main'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $result),
        );
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

        $deps = $this->deps($extractor);

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

        $deps = $this->deps($extractor);

        $result = $deps->getDependenciesForNamespace(['/src'], ['app\\main']);

        self::assertSame(
            ['phel.core', 'phel.string', 'app\\main'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $result),
        );
    }

    public function test_resolves_a_seed_with_kebab_case_segments(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('lib.phel', 'fixtures.cross-require.lib', ['phel.core']),
            ]);

        $deps = $this->deps($extractor);

        $result = $deps->getDependenciesForNamespace(['/src'], ['fixtures.cross-require.lib']);

        self::assertSame(
            ['phel.core', 'fixtures.cross-require.lib'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $result),
        );
    }

    public function test_resolves_a_seed_given_with_the_legacy_backslash_separator(): void
    {
        // `Watch`'s file-change resolver hands over the backslash form; a plain
        // string match against the canonical dot form returned nothing at all,
        // with no error, so the reload was a silent no-op.
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('lib.phel', 'fixtures.cross-require.lib', ['phel.core']),
            ]);

        $deps = $this->deps($extractor);

        $result = $deps->getDependenciesForNamespace(['/src'], ['fixtures\\cross-require\\lib']);

        self::assertSame(
            ['phel.core', 'fixtures.cross-require.lib'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $result),
        );
    }

    public function test_resolves_a_dependency_declared_with_the_legacy_backslash_separator(): void
    {
        $extractor = $this->createStub(NamespaceExtractorInterface::class);
        $extractor->method('getNamespacesFromDirectories')
            ->willReturn([
                new NamespaceInformation('core.phel', 'phel.core', []),
                new NamespaceInformation('lib.phel', 'fixtures.cross-require.lib', ['phel.core']),
                new NamespaceInformation('app.phel', 'app.main', ['phel.core', 'fixtures\\cross-require\\lib']),
            ]);

        $deps = $this->deps($extractor);

        $result = $deps->getDependenciesForNamespace(['/src'], ['app.main']);

        self::assertSame(
            ['phel.core', 'fixtures.cross-require.lib', 'app.main'],
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

        $deps = $this->deps($extractor);

        $result = $deps->getDependenciesForNamespace(['/src'], ['app\\main']);

        self::assertSame(
            ['phel.core', 'app\\main'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $result),
        );
    }

    /**
     * @param list<string> $bundled namespaces shipped on the configured source and vendor dirs
     */
    private function deps(NamespaceExtractorInterface $extractor, array $bundled = ['phel.core', 'phel.json']): DependenciesForNamespace
    {
        $bundledExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $bundledExtractor->method('getNamespacesFromDirectories')->willReturn(array_map(
            static fn(string $ns): NamespaceInformation => new NamespaceInformation($ns . '.phel', $ns, []),
            $bundled,
        ));
        $commandFacade = $this->createStub(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn(['/phel/src']);
        $commandFacade->method('getVendorSourceDirectories')->willReturn([]);

        return new DependenciesForNamespace(
            $extractor,
            new BundledNamespaceIndex($bundledExtractor, $commandFacade, new CompilerFacade()),
            new MissingRequireReporter(new CompilerFacade()),
        );
    }
}
