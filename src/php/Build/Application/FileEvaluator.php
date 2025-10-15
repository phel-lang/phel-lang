<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use RuntimeException;

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

        $code = file_get_contents($src);
        if ($code === false) {
            throw new RuntimeException(sprintf('Unable to read file "%s".', $src));
        }

        // Use eval() for runtime - compile() with caching is for build mode only
        $this->compilerFacade->eval($code, $options);

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);

        return new CompiledFile(
            $src,
            '',
            $namespaceInfo->getNamespace(),
        );
    }
}
