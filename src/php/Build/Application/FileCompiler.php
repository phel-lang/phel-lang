<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\BuildFacade;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;

use function function_exists;

final readonly class FileCompiler implements FileCompilerInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private NamespaceExtractorInterface $namespaceExtractor,
        private FileIoInterface $fileIo,
    ) {
    }

    public function compileFile(string $src, string $dest, bool $enableSourceMaps): CompiledFile
    {
        $phelCode = $this->fileIo->getContents($src);

        $options = (new CompileOptions())
            ->setSource($src)
            ->setIsEnabledSourceMaps($enableSourceMaps);

        BuildFacade::enableBuildMode();
        try {
            $result = $this->compilerFacade->compile($phelCode, $options);
        } finally {
            BuildFacade::disableBuildMode();
        }

        $phpCode = "<?php declare(strict_types=1);\n" . $result->getPhpCode();

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
        $sourceFile = str_replace('.php', '.phel', $dest);
        $this->fileIo->putContents($sourceFile, $phelCode);
    }

    private function writeSourceMap(string $dest, string $sourceMap, bool $enableSourceMaps): void
    {
        $mapFile = $dest . '.map';

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
