<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use Phel\Build\Application\BuildHealthCheck;
use Phel\Build\Application\CacheClearer;
use Phel\Build\Application\CachedNamespaceExtractor;
use Phel\Build\Application\DependenciesForNamespace;
use Phel\Build\Application\FileCompiler;
use Phel\Build\Application\FileEvaluator;
use Phel\Build\Application\NamespaceExtractor;
use Phel\Build\Application\ProjectCompiler;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;
use Phel\Build\Domain\Compile\CompiledTargetPathResolver;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFile;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Compile\Output\NamespacePathTransformer;
use Phel\Build\Domain\Compile\SecondaryFileHarvester;
use Phel\Build\Domain\Extractor\ExcludedScanPaths;
use Phel\Build\Domain\Extractor\FirstFormExtractor;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Build\Infrastructure\Cache\DependencyTracker;
use Phel\Build\Infrastructure\Cache\PhpNamespaceCache;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\VersionFinder;

/**
 * @extends AbstractFactory<BuildConfig>
 */
final class BuildFactory extends AbstractFactory
{
    public function createProjectCompiler(): ProjectCompiler
    {
        return new ProjectCompiler(
            $this->createNamespaceExtractor(),
            $this->createFileCompiler(),
            $this->getCompilerFacade(),
            $this->getCommandFacade(),
            $this->createMainPhpEntryPointFile(),
            $this->getConfig(),
            $this->createSecondaryFileHarvester(),
        );
    }

    public function createDependenciesForNamespace(): DependenciesForNamespace
    {
        return new DependenciesForNamespace(
            $this->createNamespaceExtractor(),
        );
    }

    public function createFileCompiler(): FileCompilerInterface
    {
        return new FileCompiler(
            $this->getCompilerFacade(),
            $this->createNamespaceExtractor(),
            $this->createFileIo(),
            $this->getConfig()->getOptimizationLevel(),
        );
    }

    public function createFileEvaluator(): FileEvaluator
    {
        // `singleton()` so repeated `(load ...)` calls reuse one
        // evaluator — otherwise each fresh `new BuildFacade()` would
        // rebuild its dependency tree and the on-disk compiled-code
        // index would be re-included per load.
        /** @var FileEvaluator $evaluator */
        $evaluator = $this->singleton(
            FileEvaluator::class,
            fn(): FileEvaluator => new FileEvaluator(
                $this->getCompilerFacade(),
                $this->createNamespaceExtractor(),
                $this->createCompiledCodeCache(),
                $this->createFirstFormExtractor(),
                $this->createDependencyTracker(),
                $this->getConfig()->getOptimizationLevel(),
            ),
        );
        return $evaluator;
    }

    public function createNamespaceExtractor(): NamespaceExtractorInterface
    {
        /** @var NamespaceExtractorInterface $extractor */
        $extractor = $this->singleton(
            NamespaceExtractorInterface::class,
            function (): NamespaceExtractorInterface {
                $excludedPaths = $this->createExcludedScanPaths();

                $innerExtractor = new NamespaceExtractor(
                    $this->getCompilerFacade(),
                    $this->createNamespaceSorter(),
                    $this->createFileIo(),
                    $excludedPaths,
                );

                if (!$this->getConfig()->isNamespaceCacheEnabled()) {
                    return $innerExtractor;
                }

                return new CachedNamespaceExtractor(
                    $innerExtractor,
                    $this->createNamespaceCache(),
                    $this->createNamespaceSorter(),
                    $excludedPaths,
                );
            },
        );
        return $extractor;
    }

    public function createCacheClearer(): CacheClearer
    {
        return new CacheClearer(
            $this->getConfig()->getTempDir(),
            $this->getConfig()->getCacheDir(),
        );
    }

    public function createBuildHealthCheck(): ModuleHealthCheckInterface
    {
        return new BuildHealthCheck(
            $this->getConfig()->getCacheDir(),
            $this->getCommandFacade()->getOutputDirectory(),
            $this->getCommandFacade()->getSourceDirectories(),
        );
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        /** @var CompilerFacadeInterface $facade */
        $facade = $this->getProvidedDependency(BuildProvider::FACADE_COMPILER);
        return $facade;
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        /** @var CommandFacadeInterface $facade */
        $facade = $this->getProvidedDependency(BuildProvider::FACADE_COMMAND);
        return $facade;
    }

    private function createSecondaryFileHarvester(): ?SecondaryFileHarvester
    {
        $compiledCodeCache = $this->createCompiledCodeCache();
        if (!$compiledCodeCache instanceof CompiledCodeCache) {
            return null;
        }

        return new SecondaryFileHarvester(
            $compiledCodeCache,
            new CompiledTargetPathResolver($this->getCompilerFacade()),
            $this->createFileIo(),
        );
    }

    private function createCompiledCodeCache(): ?CompiledCodeCache
    {
        /** @var CompiledCodeCache|null $cache */
        $cache = $this->singleton(
            CompiledCodeCache::class,
            fn(): ?CompiledCodeCache => $this->buildCompiledCodeCache(),
        );
        return $cache;
    }

    private function buildCompiledCodeCache(): ?CompiledCodeCache
    {
        if (!$this->getConfig()->isCompiledCodeCacheEnabled()) {
            return null;
        }

        return new CompiledCodeCache(
            $this->getConfig()->getCacheDir(),
            VersionFinder::LATEST_VERSION,
        );
    }

    private function createDependencyTracker(): ?DependencyTracker
    {
        /** @var DependencyTracker|null $tracker */
        $tracker = $this->singleton(
            DependencyTracker::class,
            fn(): ?DependencyTracker => $this->getConfig()->isCompiledCodeCacheEnabled()
                ? new DependencyTracker($this->getConfig()->getCacheDir())
                : null,
        );
        return $tracker;
    }

    private function createNamespaceCache(): NamespaceCacheInterface
    {
        return new PhpNamespaceCache($this->getConfig()->getNamespaceCacheFile());
    }

    private function createMainPhpEntryPointFile(): EntryPointPhpFileInterface
    {
        return new EntryPointPhpFile(
            $this->getConfig()->getPhelBuildConfig(),
            $this->createNamespacePathTransformer(),
            $this->getConfig()->getAppRootDir(),
        );
    }

    private function createNamespaceSorter(): NamespaceSorterInterface
    {
        return new TopologicalNamespaceSorter();
    }

    /**
     * Compiled output contains a `.phel` source copy next to every generated
     * `.php`; excluding that dir prevents duplicate-namespace shadowing when a
     * scan root (e.g. cwd) sits above it.
     */
    private function createExcludedScanPaths(): ExcludedScanPaths
    {
        $outputDirectory = $this->getCommandFacade()->getOutputDirectory();

        return new ExcludedScanPaths(
            excludedDirectories: [$outputDirectory],
            destDirBasename: basename($outputDirectory),
        );
    }

    private function createFileIo(): FileIoInterface
    {
        return new SystemFileIo();
    }

    private function createNamespacePathTransformer(): NamespacePathTransformer
    {
        return new NamespacePathTransformer();
    }

    private function createFirstFormExtractor(): FirstFormExtractor
    {
        return new FirstFormExtractor();
    }
}
