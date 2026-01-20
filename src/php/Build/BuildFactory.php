<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFactory;
use Phel\Build\Application\CacheClearer;
use Phel\Build\Application\CachedNamespaceExtractor;
use Phel\Build\Application\DependenciesForNamespace;
use Phel\Build\Application\FileCompiler;
use Phel\Build\Application\FileEvaluator;
use Phel\Build\Application\NamespaceExtractor;
use Phel\Build\Application\Port\CompileProjectUseCase;
use Phel\Build\Application\ProjectCompiler;
use Phel\Build\Application\UseCase\CompileProjectHandler;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFile;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Compile\Output\NamespacePathTransformer;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Build\Domain\Port\FileDiscovery\PhelFileDiscoveryPort;
use Phel\Build\Domain\Port\FileSystem\FileSystemPort;
use Phel\Build\Domain\Service\CacheEligibilityChecker;
use Phel\Build\Domain\Service\NamespaceFilter;
use Phel\Build\Domain\ValueObject\BuildContext;
use Phel\Build\Infrastructure\Adapter\FileDiscovery\RecursivePhelFileDiscovery;
use Phel\Build\Infrastructure\Adapter\FileSystem\LocalFileSystemAdapter;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Build\Infrastructure\Cache\PhpNamespaceCache;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Console\Application\VersionFinder;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;

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
            $this->createNamespaceFilter(),
            $this->createCacheEligibilityChecker(),
            $this->createBuildContext(),
        );
    }

    public function createCompileProjectHandler(): CompileProjectUseCase
    {
        return new CompileProjectHandler(
            $this->createNamespaceExtractor(),
            $this->createFileCompiler(),
            $this->getCompilerFacade(),
            $this->getCommandFacade(),
            $this->createMainPhpEntryPointFile(),
            $this->getConfig(),
            $this->createNamespaceFilter(),
            $this->createCacheEligibilityChecker(),
            $this->createBuildContext(),
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
            $this->createBuildContext(),
        );
    }

    public function createFileEvaluator(): FileEvaluator
    {
        return new FileEvaluator(
            $this->getCompilerFacade(),
            $this->createNamespaceExtractor(),
            $this->createCompiledCodeCache(),
        );
    }

    public function createNamespaceExtractor(): NamespaceExtractorInterface
    {
        $fileDiscovery = $this->createPhelFileDiscoveryPort();

        $innerExtractor = new NamespaceExtractor(
            $this->getCompilerFacade(),
            $this->createNamespaceSorter(),
            $this->createFileIo(),
            $fileDiscovery,
        );

        if (!$this->getConfig()->isNamespaceCacheEnabled()) {
            return $innerExtractor;
        }

        return new CachedNamespaceExtractor(
            $innerExtractor,
            $this->createNamespaceCache(),
            $this->createNamespaceSorter(),
            $fileDiscovery,
        );
    }

    public function createCacheClearer(): CacheClearer
    {
        return new CacheClearer(
            $this->getConfig()->getTempDir(),
            $this->getConfig()->getCacheDir(),
        );
    }

    public function createNamespaceFilter(): NamespaceFilter
    {
        return new NamespaceFilter(
            $this->getConfig()->getPathsToIgnore(),
        );
    }

    public function createCacheEligibilityChecker(): CacheEligibilityChecker
    {
        return new CacheEligibilityChecker(
            $this->createFileSystemPort(),
            $this->getConfig()->getPathsToAvoidCache(),
        );
    }

    public function createBuildContext(): BuildContext
    {
        return new BuildContext();
    }

    public function createFileSystemPort(): FileSystemPort
    {
        return new LocalFileSystemAdapter();
    }

    public function createPhelFileDiscoveryPort(): PhelFileDiscoveryPort
    {
        return new RecursivePhelFileDiscovery();
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(BuildProvider::FACADE_COMPILER);
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(BuildProvider::FACADE_COMMAND);
    }

    private function createCompiledCodeCache(): ?CompiledCodeCache
    {
        if (!$this->getConfig()->isCompiledCodeCacheEnabled()) {
            return null;
        }

        return new CompiledCodeCache(
            $this->getConfig()->getCacheDir(),
            VersionFinder::LATEST_VERSION,
        );
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

    private function createFileIo(): FileIoInterface
    {
        return new SystemFileIo();
    }

    private function createNamespacePathTransformer(): NamespacePathTransformer
    {
        return new NamespacePathTransformer();
    }
}
