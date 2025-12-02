<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFactory;
use Phel\Build\Application\CachedNamespaceExtractor;
use Phel\Build\Application\DependenciesForNamespace;
use Phel\Build\Application\FileCompiler;
use Phel\Build\Application\FileEvaluator;
use Phel\Build\Application\NamespaceExtractor;
use Phel\Build\Application\ProjectCompiler;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFile;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Compile\Output\NamespacePathTransformer;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Build\Infrastructure\Cache\JsonNamespaceCache;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;

/**
 * @extends AbstractFactory<BuildConfig>
 */
final class BuildFactory extends AbstractFactory
{
    private static ?NamespaceCacheInterface $namespaceCache = null;

    public function createProjectCompiler(): ProjectCompiler
    {
        return new ProjectCompiler(
            $this->createNamespaceExtractor(),
            $this->createFileCompiler(),
            $this->getCompilerFacade(),
            $this->getCommandFacade(),
            $this->createMainPhpEntryPointFile(),
            $this->getConfig(),
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
        );
    }

    public function createFileEvaluator(): FileEvaluator
    {
        return new FileEvaluator(
            $this->getCompilerFacade(),
            $this->createNamespaceExtractor(),
        );
    }

    public function createNamespaceExtractor(): NamespaceExtractorInterface
    {
        $innerExtractor = new NamespaceExtractor(
            $this->getCompilerFacade(),
            $this->createNamespaceSorter(),
            $this->createFileIo(),
        );

        if (!$this->getConfig()->isNamespaceCacheEnabled()) {
            return $innerExtractor;
        }

        return new CachedNamespaceExtractor(
            $innerExtractor,
            $this->createNamespaceCache(),
            $this->createNamespaceSorter(),
        );
    }

    public function createNamespaceCache(): NamespaceCacheInterface
    {
        if (!self::$namespaceCache instanceof NamespaceCacheInterface) {
            self::$namespaceCache = JsonNamespaceCache::load($this->getConfig()->getNamespaceCacheFile());
        }

        return self::$namespaceCache;
    }

    public static function clearNamespaceCacheInstance(): void
    {
        self::$namespaceCache = null;
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(BuildProvider::FACADE_COMPILER);
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(BuildProvider::FACADE_COMMAND);
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
