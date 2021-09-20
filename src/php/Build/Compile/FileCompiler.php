<?php

declare(strict_types=1);

namespace Phel\Build\Compile;

use Phel\Build\Extractor\NamespaceExtractor;
use Phel\Compiler\CompilerFacadeInterface;

final class FileCompiler
{
    private CompilerFacadeInterface $compilerFacade;

    private NamespaceExtractor $namespaceExtractor;

    public function __construct(
        CompilerFacadeInterface $compilerFacade,
        NamespaceExtractor $namespaceExtractor
    ) {
        $this->compilerFacade = $compilerFacade;
        $this->namespaceExtractor = $namespaceExtractor;
    }

    public function compileFile(string $src, string $dest): CompiledFile
    {
        $this->compilerFacade->compile(
            file_get_contents($src),
            $src,
            true
        );

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new CompiledFile(
            $src,
            $dest,
            $namespaceInfo->getNamespace()
        );
    }
}
