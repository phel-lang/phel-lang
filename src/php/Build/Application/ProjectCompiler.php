<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\BuildConfigInterface;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Event\BuildCompletedEvent;
use Phel\Build\Domain\Event\BuildStartedEvent;
use Phel\Build\Domain\Event\FileCompiledEvent;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Port\EventDispatcher\BuildEventDispatcherPort;
use Phel\Build\Domain\Service\CacheEligibilityChecker;
use Phel\Build\Domain\Service\NamespaceFilter;
use Phel\Build\Domain\ValueObject\BuildContext;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use RuntimeException;

use function count;
use function dirname;
use function sprintf;

/**
 * Application service for compiling Phel projects.
 * Orchestrates the build process by delegating to domain services.
 */
final readonly class ProjectCompiler
{
    private const string TARGET_FILE_EXTENSION = '.php';

    public function __construct(
        private NamespaceExtractorInterface $namespaceExtractor,
        private FileCompilerInterface $fileCompiler,
        private CompilerFacadeInterface $compilerFacade,
        private CommandFacadeInterface $commandFacade,
        private EntryPointPhpFileInterface $entryPointPhpFile,
        private BuildConfigInterface $config,
        private NamespaceFilter $namespaceFilter,
        private CacheEligibilityChecker $cacheEligibilityChecker,
        private BuildContext $buildContext,
        private BuildEventDispatcherPort $eventDispatcher,
    ) {
    }

    /**
     * @return list<CompiledFile>
     */
    public function compileProject(BuildOptions $buildOptions): array
    {

        $srcDirectories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        $dest = $this->commandFacade->getOutputDirectory();

        return $this->compileFromTo($srcDirectories, $dest, $buildOptions);
    }

    /**
     * @param list<string> $srcDirectories
     *
     * @return list<CompiledFile>
     */
    private function compileFromTo(array $srcDirectories, string $dest, BuildOptions $buildOptions): array
    {
        $this->eventDispatcher->dispatch(new BuildStartedEvent($srcDirectories, $dest));

        // Initialize the GlobalEnvironment before loading cached files.
        // This ensures Phel::clear() is called before any definitions are registered,
        // preventing definitions from being lost when compilation is triggered later.
        $this->compilerFacade->initializeGlobalEnvironment();

        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($srcDirectories);
        /** @var list<CompiledFile> $result */
        $result = [];
        /** @var list<string> $compiledFiles */
        $compiledFiles = [];
        $cachedFiles = 0;

        foreach ($namespaceInformation as $info) {
            if ($this->namespaceFilter->shouldIgnore($info)) {
                continue;
            }

            $targetFile = $dest . '/' . $this->getTargetFileFromNamespace($info->getNamespace());
            $targetDir = dirname($targetFile);
            if (!file_exists($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
            }

            if ($this->cacheEligibilityChecker->canUseCache($buildOptions, $targetFile, $info)) {
                // Load cached file to register definitions and execute top-level expressions
                $this->buildContext->executeInBuildMode(static function () use ($targetFile): void {
                    /** @psalm-suppress UnresolvableInclude */
                    require_once $targetFile;
                });

                ++$cachedFiles;

                continue;
            }

            $result[] = $this->fileCompiler->compileFile(
                $info->getFile(),
                $targetFile,
                $buildOptions->isSourceMapEnabled(),
            );

            touch($targetFile, $this->getFileMtime($info->getFile()));

            $compiledFiles[] = $info->getFile();
            $this->eventDispatcher->dispatch(new FileCompiledEvent(
                $info->getFile(),
                $targetFile,
                $info->getNamespace(),
            ));
        }

        if ($this->config->shouldCreateEntryPointPhpFile()) {
            $this->entryPointPhpFile->createFile();
        }

        $this->eventDispatcher->dispatch(new BuildCompletedEvent(
            count($namespaceInformation),
            $compiledFiles,
            $cachedFiles,
        ));

        return $result;
    }

    private function getTargetFileFromNamespace(string $namespace): string
    {
        $mungedNamespace = $this->compilerFacade->encodeNs($namespace);

        return implode(DIRECTORY_SEPARATOR, explode('\\', $mungedNamespace)) . self::TARGET_FILE_EXTENSION;
    }

    private function getFileMtime(string $file): int
    {
        $mtime = filemtime($file);

        if ($mtime === false) {
            throw new RuntimeException(sprintf('Unable to read file modification time for "%s".', $file));
        }

        return $mtime;
    }
}
