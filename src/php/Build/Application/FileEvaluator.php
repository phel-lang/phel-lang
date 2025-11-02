<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use RuntimeException;

use function file_get_contents;
use function sprintf;

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

        $sourceCode = file_get_contents($src);
        if ($sourceCode === false) {
            throw new RuntimeException(sprintf('Unable to read file "%s".', $src));
        }

        // Evaluate the source code (full compilation + evaluation pipeline)
        // Note: RequireEvaluator will cache compiled PHP snippets via CompiledCodeCache
        $this->compilerFacade->eval($sourceCode, $options);

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new CompiledFile(
            $src,
            '',
            $namespaceInfo->getNamespace(),
        );
    }
}
