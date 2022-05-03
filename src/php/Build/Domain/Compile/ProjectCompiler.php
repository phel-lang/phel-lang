<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacadeInterface;

use function dirname;

final class ProjectCompiler
{
    private const TARGET_FILE_EXTENSION = '.php';

    private NamespaceExtractorInterface $namespaceExtractor;

    private FileCompilerInterface $fileCompiler;

    private CompilerFacadeInterface $compilerFacade;

    private CommandFacadeInterface $commandFacade;

    public function __construct(
        NamespaceExtractorInterface $namespaceExtractor,
        FileCompilerInterface $fileCompiler,
        CompilerFacadeInterface $compilerFacade,
        CommandFacadeInterface $commandFacade
    ) {
        $this->namespaceExtractor = $namespaceExtractor;
        $this->fileCompiler = $fileCompiler;
        $this->compilerFacade = $compilerFacade;
        $this->commandFacade = $commandFacade;
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

    private function getTargetFileFromNamespace(string $namespace): string
    {
        $mungedNamespace = $this->compilerFacade->encodeNs($namespace);

        return implode(DIRECTORY_SEPARATOR, explode('\\', $mungedNamespace)) . self::TARGET_FILE_EXTENSION;
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
            $targetFile = $dest . '/' . $this->getTargetFileFromNamespace($info->getNamespace());
            $targetDir = dirname($targetFile);
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            if ($buildOptions->isCacheEnabled()
                && file_exists($targetFile)
                && filemtime($targetFile) === filemtime($info->getFile())
            ) {
                /** @psalm-suppress UnresolvableInclude */
                require_once $targetFile;
                continue;
            }

            $result[] = $this->fileCompiler->compileFile(
                $info->getFile(),
                $targetFile,
                $buildOptions->isSourceMapEnabled()
            );

            touch($targetFile, filemtime($info->getFile()));
        }

        return $result;
    }
}
