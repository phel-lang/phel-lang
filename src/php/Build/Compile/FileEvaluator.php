<?php

declare(strict_types=1);

namespace Phel\Build\Compile;

use Phel\Build\Extractor\NamespaceExtractor;
use Phel\Compiler\Compiler\CompileOptions;
use Phel\Compiler\CompilerFacadeInterface;

final class FileEvaluator
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

    public function evalFile(string $src): CompiledFile
    {
        $options = (new CompileOptions())
            ->setSource($src)
            ->setIsEnabledSourceMaps(true);

        $this->compilerFacade->eval(file_get_contents($src), $options);

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new CompiledFile(
            $src,
            '',
            $namespaceInfo->getNamespace()
        );
    }
}
