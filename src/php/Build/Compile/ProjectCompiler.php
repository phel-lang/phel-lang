<?php

declare(strict_types=1);

namespace Phel\Build\Compile;

use Phel\Build\Extractor\NamespaceExtractorInterface;
use Phel\Compiler\CompilerFacadeInterface;

final class ProjectCompiler
{
    private const TARGET_FILE_EXTENSION = '.php';

    private NamespaceExtractorInterface $namespaceExtractor;

    private FileCompilerInterface $fileCompiler;

    private CompilerFacadeInterface $compilerFacade;

    public function __construct(
        NamespaceExtractorInterface $namespaceExtractor,
        FileCompilerInterface $fileCompiler,
        CompilerFacadeInterface $compilerFacade
    ) {
        $this->namespaceExtractor = $namespaceExtractor;
        $this->fileCompiler = $fileCompiler;
        $this->compilerFacade = $compilerFacade;
    }

    /**
     * @return list<CompiledFile>
     */
    public function compileProject(array $srcDirectories, string $dest, BuildOptions $buildOptions): array
    {
        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($srcDirectories);

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

    private function getTargetFileFromNamespace(string $namespace): string
    {
        $mungedNamespace = $this->compilerFacade->encodeNs($namespace);

        return implode(DIRECTORY_SEPARATOR, explode('\\', $mungedNamespace)) . self::TARGET_FILE_EXTENSION;
    }
}
