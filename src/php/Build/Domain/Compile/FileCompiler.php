<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Build\BuildFacade;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Transpiler\Infrastructure\CompileOptions;
use Phel\Transpiler\TranspilerFacadeInterface;

final readonly class FileCompiler implements FileCompilerInterface
{
    public function __construct(
        private TranspilerFacadeInterface $compilerFacade,
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

        $this->fileIo->putContents($dest, "<?php\n" . $result->getPhpCode());
        $this->fileIo->putContents(str_replace('.php', '.phel', $dest), $phelCode);

        if ($enableSourceMaps) {
            $this->fileIo->putContents($dest . '.map', $result->getSourceMap());
        } else {
            $this->fileIo->removeFile($dest . '.map');
        }

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new CompiledFile(
            $src,
            $dest,
            $namespaceInfo->getNamespace(),
        );
    }
}
