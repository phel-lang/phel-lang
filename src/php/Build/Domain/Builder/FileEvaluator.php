<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Builder;

use Phel\Build\Domain\Extractor\NamespaceExtractor;
use Phel\Transpiler\Infrastructure\TranspileOptions;
use Phel\Transpiler\TranspilerFacadeInterface;

final readonly class FileEvaluator
{
    public function __construct(
        private TranspilerFacadeInterface $compilerFacade,
        private NamespaceExtractor $namespaceExtractor,
    ) {
    }

    public function evalFile(string $src): TraspiledFile
    {
        $options = (new TranspileOptions())
            ->setSource($src)
            ->setIsEnabledSourceMaps(true);

        $this->compilerFacade->eval(file_get_contents($src), $options);

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new TraspiledFile(
            $src,
            '',
            $namespaceInfo->getNamespace(),
        );
    }
}
