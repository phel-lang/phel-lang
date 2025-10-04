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
        $result = $this->compilerFacade->compile($phelCode, $options);
        BuildFacade::disableBuildMode();

        $this->fileIo->putContents(
            $dest,
            "<?php declare(strict_types=1);\n" . $result->getPhpCode(),
        );
        $this->fileIo->putContents(str_replace('.php', '.phel', $dest), $phelCode);

        if ($enableSourceMaps) {
            $this->fileIo->putContents($dest . '.map', $result->getSourceMap());
        } else {
            $this->fileIo->removeFile($dest . '.map');
        }

        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($dest);
        }

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new CompiledFile(
            $src,
            $dest,
            $namespaceInfo->getNamespace(),
        );
    }
}
