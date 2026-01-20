<?php

declare(strict_types=1);

namespace Phel\Build\Application\UseCase;

use Phel\Build\Application\Port\CompileProjectUseCase;
use Phel\Build\BuildConfigInterface;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Service\CacheEligibilityChecker;
use Phel\Build\Domain\Service\NamespaceFilter;
use Phel\Build\Domain\Transfer\CompiledFileTransfer;
use Phel\Build\Domain\ValueObject\BuildContext;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use RuntimeException;

use function dirname;
use function sprintf;

/**
 * Application handler implementing the CompileProjectUseCase.
 * Orchestrates compilation by delegating to domain services.
 */
final readonly class CompileProjectHandler implements CompileProjectUseCase
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
    ) {
    }

    /**
     * @return list<CompiledFileTransfer>
     */
    public function execute(BuildOptions $options): array
    {
        $srcDirectories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        $dest = $this->commandFacade->getOutputDirectory();

        return $this->compileFromTo($srcDirectories, $dest, $options);
    }

    /**
     * @param list<string> $srcDirectories
     *
     * @return list<CompiledFileTransfer>
     */
    private function compileFromTo(array $srcDirectories, string $dest, BuildOptions $buildOptions): array
    {
        // Initialize the GlobalEnvironment before loading cached files.
        $this->compilerFacade->initializeGlobalEnvironment();

        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($srcDirectories);
        /** @var list<CompiledFileTransfer> $result */
        $result = [];

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
                $this->buildContext->executeInBuildMode(static function () use ($targetFile): void {
                    /** @psalm-suppress UnresolvableInclude */
                    require_once $targetFile;
                });

                continue;
            }

            $compiledFile = $this->fileCompiler->compileFile(
                $info->getFile(),
                $targetFile,
                $buildOptions->isSourceMapEnabled(),
            );

            $result[] = new CompiledFileTransfer(
                $compiledFile->getSourceFile(),
                $compiledFile->getTargetFile(),
                $compiledFile->getNamespace(),
            );

            touch($targetFile, $this->getFileMtime($info->getFile()));
        }

        if ($this->config->shouldCreateEntryPointPhpFile()) {
            $this->entryPointPhpFile->createFile();
        }

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
