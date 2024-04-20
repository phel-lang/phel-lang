<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Builder;

use Phel\Build\BuildFacade;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Transpiler\Infrastructure\TranspileOptions;
use Phel\Transpiler\TranspilerFacadeInterface;

final readonly class FileBuilder implements FileBuilderInterface
{
    public function __construct(
        private TranspilerFacadeInterface $compilerFacade,
        private NamespaceExtractorInterface $namespaceExtractor,
        private FileIoInterface $fileIo,
    ) {
    }

    public function compileFile(string $src, string $dest, bool $enableSourceMaps): TraspiledFile
    {
        $phelCode = $this->fileIo->getContents($src);

        $options = (new TranspileOptions())
            ->setSource($src)
            ->setIsEnabledSourceMaps($enableSourceMaps);

        BuildFacade::enableBuildMode();
        $result = $this->compilerFacade->transpile($phelCode, $options);
        BuildFacade::disableBuildMode();

        $this->fileIo->putContents($dest, "<?php\n" . $result->getPhpCode());
        $this->fileIo->putContents(str_replace('.php', '.phel', $dest), $phelCode);

        if ($enableSourceMaps) {
            $this->fileIo->putContents($dest . '.map', $result->getSourceMap());
        } else {
            $this->fileIo->removeFile($dest . '.map');
        }

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new TraspiledFile(
            $src,
            $dest,
            $namespaceInfo->getNamespace(),
        );
    }
}
