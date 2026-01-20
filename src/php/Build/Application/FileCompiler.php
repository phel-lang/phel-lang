<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Build\Domain\Port\Compiler\PhelCompilerPort;
use Phel\Build\Domain\Transfer\CompilationResultTransfer;
use Phel\Build\Domain\ValueObject\BuildContext;

use function function_exists;

final readonly class FileCompiler implements FileCompilerInterface
{
    public function __construct(
        private PhelCompilerPort $compilerPort,
        private NamespaceExtractorInterface $namespaceExtractor,
        private FileIoInterface $fileIo,
        private BuildContext $buildContext,
    ) {
    }

    public function compileFile(string $src, string $dest, bool $enableSourceMaps): CompiledFile
    {
        $phelCode = $this->fileIo->getContents($src);

        $result = $this->buildContext->executeInBuildMode(
            fn (): CompilationResultTransfer => $this->compilerPort->compile($phelCode, $src, $enableSourceMaps),
        );

        $phpCode = "<?php declare(strict_types=1);\n" . $result->phpCode;

        $this->fileIo->putContents($dest, $phpCode);
        $this->writeSourceReference($dest, $phelCode);
        $this->writeSourceMap($dest, $result->sourceMap, $enableSourceMaps);
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
