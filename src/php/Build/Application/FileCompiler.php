<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\BuildFacade;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\SourceMap\BuiltFilePreamble;
use Phel\Shared\SourceMap\SourceMapSiblings;

use function function_exists;

final readonly class FileCompiler implements FileCompilerInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private NamespaceExtractorInterface $namespaceExtractor,
        private FileIoInterface $fileIo,
        private int $defaultOptimizationLevel = CompileOptions::DEFAULT_OPTIMIZATION_LEVEL,
    ) {}

    public function compileFile(string $src, string $dest, bool $enableSourceMaps, ?int $optimizationLevel = null): CompiledFile
    {
        $phelCode = $this->fileIo->getContents($src);

        $options = new CompileOptions()
            ->setSource($src)
            ->setIsEnabledSourceMaps($enableSourceMaps)
            ->setOptimizationLevel($optimizationLevel ?? $this->defaultOptimizationLevel);

        BuildFacade::enableBuildMode();
        ob_start();
        try {
            $result = $this->compilerFacade->compile($phelCode, $options);
        } finally {
            ob_end_clean();
            BuildFacade::disableBuildMode();
        }

        $phpCode = BuiltFilePreamble::prepend($result->getPhpCode());

        $this->fileIo->putContents($dest, $phpCode);
        $this->writeSourceReference($dest, $phelCode);
        $this->writeSourceMap($dest, $result->getSourceMap(), $enableSourceMaps);
        $this->compileWithOpcache($dest);

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new CompiledFile(
            $src,
            $dest,
            $namespaceInfo->getNamespace(),
        );
    }

    private function writeSourceReference(string $dest, string $phelCode): void
    {
        $sourceFile = SourceMapSiblings::sourceFile($dest);
        $this->fileIo->putContents($sourceFile, $phelCode);
    }

    private function writeSourceMap(string $dest, string $sourceMap, bool $enableSourceMaps): void
    {
        $mapFile = SourceMapSiblings::mapFile($dest);

        if ($enableSourceMaps) {
            $this->fileIo->putContents($mapFile, $sourceMap);
        } else {
            $this->fileIo->removeFile($mapFile);
        }
    }

    private function compileWithOpcache(string $filename): void
    {
        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($filename);
        }
    }
}
