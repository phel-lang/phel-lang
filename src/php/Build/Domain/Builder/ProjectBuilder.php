<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Builder;

use Phel\Build\BuildConfigInterface;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Builder\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Command\CommandFacadeInterface;
use Phel\Transpiler\TranspilerFacadeInterface;
use RuntimeException;

use function dirname;

final readonly class ProjectBuilder
{
    private const TARGET_FILE_EXTENSION = '.php';

    public function __construct(
        private NamespaceExtractorInterface $namespaceExtractor,
        private FileTranspilerInterface $fileTranspiler,
        private TranspilerFacadeInterface $transpilerFacade,
        private CommandFacadeInterface $commandFacade,
        private EntryPointPhpFileInterface $entryPointPhpFile,
        private BuildConfigInterface $config,
    ) {
    }

    /**
     * @return list<TraspiledFile>
     */
    public function buildProject(BuildOptions $buildOptions): array
    {
        $srcDirectories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        $dest = $this->commandFacade->getOutputDirectory();

        return $this->transpileFromTo($srcDirectories, $dest, $buildOptions);
    }

    /**
     * @return list<TraspiledFile>
     */
    private function transpileFromTo(array $srcDirectories, string $dest, BuildOptions $buildOptions): array
    {
        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($srcDirectories);
        /** @var list<TraspiledFile> $result */
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

            $result[] = $this->fileTranspiler->transpileFile(
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
        $mungedNamespace = $this->transpilerFacade->encodeNs($namespace);

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
