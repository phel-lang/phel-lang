<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Infrastructure\CompileOptions;

final readonly class FileEvaluator
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private NamespaceExtractor $namespaceExtractor,
    ) {
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
            $namespaceInfo->getNamespace(),
        );
    }
}
