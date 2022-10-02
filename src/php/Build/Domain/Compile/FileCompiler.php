<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Lang\Registry;

final class FileCompiler implements FileCompilerInterface
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

        Registry::getInstance()->addDefinition("phel\core", '*compile-mode*', true);
        $result = $this->compilerFacade->compile($phelCode, $options);
        Registry::getInstance()->addDefinition("phel\core", '*compile-mode*', false);

        file_put_contents($dest, "<?php\n" . $result->getCode());
        file_put_contents(str_replace('.php', '.phel', $dest), $phelCode);
        if ($enableSourceMaps) {
            file_put_contents($dest . '.map', $result->getSourceMap());
        } elseif (file_exists($dest . '.map')) {
            @unlink($dest . '.map');
        }

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new CompiledFile(
            $src,
            $dest,
            $namespaceInfo->getNamespace(),
        );
    }
}
