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
use Throwable;

use function file_get_contents;
use function function_exists;
use function md5;

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
        $result = $this->compilerFacade->compile($phelCode, $options);
        BuildFacade::disableBuildMode();

        $phpCode = "<?php declare(strict_types=1);\n" . $result->getPhpCode();

        $this->writeFileIfChanged($dest, $phpCode);
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

    /**
     * Writes PHP code only if the content has changed, avoiding redundant writes.
     */
    private function writeFileIfChanged(string $filename, string $phpCode): void
    {
        if ($this->fileContentChanged($filename, $phpCode)) {
            $this->fileIo->putContents($filename, $phpCode);
        }
    }

    /**
     * Checks if file content differs from the new content using MD5 hashing.
     */
    private function fileContentChanged(string $filename, string $newContent): bool
    {
        if (!file_exists($filename)) {
            return true;
        }

        try {
            $existingContent = file_get_contents($filename);
            return $existingContent === false || md5($existingContent) !== md5($newContent);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Writes the original Phel source code for debugging and reference.
     */
    private function writeSourceReference(string $dest, string $phelCode): void
    {
        $sourceFile = str_replace('.php', '.phel', $dest);
        if ($this->fileContentChanged($sourceFile, $phelCode)) {
            $this->fileIo->putContents($sourceFile, $phelCode);
        }
    }

    /**
     * Manages source map files based on enablement flag.
     */
    private function writeSourceMap(string $dest, string $sourceMap, bool $enableSourceMaps): void
    {
        $mapFile = $dest . '.map';

        if ($enableSourceMaps) {
            if ($this->fileContentChanged($mapFile, $sourceMap)) {
                $this->fileIo->putContents($mapFile, $sourceMap);
            }
        } else {
            $this->fileIo->removeFile($mapFile);
        }
    }

    /**
     * Compiles the file with OPCache if available.
     */
    private function compileWithOpcache(string $filename): void
    {
        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($filename);
        }
    }
}
