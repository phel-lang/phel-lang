<?php

declare(strict_types=1);

namespace Phel\Build\Compile;

use Phel\Build\Extractor\NamespaceExtractorInterface;
use Phel\Compiler\Emitter\OutputEmitter\MungeInterface;

final class ProjectCompiler
{
    private NamespaceExtractorInterface $namespaceExtractor;

    private FileCompilerInterface $fileCompiler;

    private MungeInterface $munge;

    public function __construct(
        NamespaceExtractorInterface $namespaceExtractor,
        FileCompilerInterface $fileCompiler,
        MungeInterface $munge
    ) {
        $this->namespaceExtractor = $namespaceExtractor;
        $this->fileCompiler = $fileCompiler;
        $this->munge = $munge;
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
        $mungedNamespace = $this->munge->encodeNs($namespace);
        return implode(DIRECTORY_SEPARATOR, explode('\\', $mungedNamespace)) . '.php';
    }
}
