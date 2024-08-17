<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\BuildConfigInterface;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacadeInterface;
use RuntimeException;

use function dirname;
use function sprintf;

final readonly class ProjectCompiler
{
    private const TARGET_FILE_EXTENSION = '.php';

    public function __construct(
        private NamespaceExtractorInterface $namespaceExtractor,
        private FileCompilerInterface $fileCompiler,
        private CompilerFacadeInterface $compilerFacade,
        private CommandFacadeInterface $commandFacade,
        private EntryPointPhpFileInterface $entryPointPhpFile,
        private BuildConfigInterface $config,
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
     * @return list<CompiledFile>
     */
    private function compileFromTo(array $srcDirectories, string $dest, BuildOptions $buildOptions): array
    {
        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($srcDirectories);
        /** @var list<CompiledFile> $result */
        $result = [];
        foreach ($namespaceInformation as $info) {
            if ($this->shouldIgnoreNs($info)) {
                continue;
            }

            $targetFile = $dest . '/' . $this->getTargetFileFromNamespace($info->getNamespace());
            $targetDir = dirname($targetFile);
            if (!file_exists($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
            }

            if ($this->canUseCache($buildOptions, $targetFile, $info)) {
                BuildFacade::enableBuildMode();
                /** @psalm-suppress UnresolvableInclude */
                require_once $targetFile;
                BuildFacade::disableBuildMode();
                continue;
            }

            $result[] = $this->fileCompiler->compileFile(
                $info->getFile(),
                $targetFile,
                $buildOptions->isSourceMapEnabled(),
            );

            touch($targetFile, filemtime($info->getFile()));
        }

        if ($this->config->shouldCreateEntryPointPhpFile()) {
            $this->entryPointPhpFile->createFile();
        }

        return $result;
    }

    private function shouldIgnoreNs(NamespaceInformation $info): bool
    {
        foreach ($this->config->getPathsToIgnore() as $path) {
            if (str_contains($info->getFile(), $path)) {
                return true;
            }
        }

        return false;
    }

    private function getTargetFileFromNamespace(string $namespace): string
    {
        $mungedNamespace = $this->compilerFacade->encodeNs($namespace);

        return implode(DIRECTORY_SEPARATOR, explode('\\', $mungedNamespace)) . self::TARGET_FILE_EXTENSION;
    }

    private function canUseCache(
        BuildOptions $buildOptions,
        string $targetFile,
        NamespaceInformation $info,
    ): bool {
        if (!$buildOptions->isCacheEnabled()
            || !file_exists($targetFile)
            || filemtime($targetFile) !== filemtime($info->getFile())
        ) {
            return false;
        }

        foreach ($this->config->getPathsToAvoidCache() as $path) {
            if (str_contains($targetFile, $path)) {
                return false;
            }
        }

        return true;
    }
}
