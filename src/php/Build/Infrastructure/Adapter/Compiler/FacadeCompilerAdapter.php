<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Adapter\Compiler;

use Phel\Build\Domain\Port\Compiler\PhelCompilerPort;
use Phel\Build\Domain\Transfer\CompilationResultTransfer;
use Phel\Compiler\Domain\ValueObject\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;

/**
 * Adapter implementing PhelCompilerPort using the CompilerFacade.
 * Translates between Build module's domain language and Compiler module's interface.
 */
final readonly class FacadeCompilerAdapter implements PhelCompilerPort
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {
    }

    public function compile(
        string $phelCode,
        string $sourcePath,
        bool $enableSourceMaps,
    ): CompilationResultTransfer {
        $options = (new CompileOptions())
            ->setSource($sourcePath)
            ->setIsEnabledSourceMaps($enableSourceMaps);

        $result = $this->compilerFacade->compile($phelCode, $options);

        return CompilationResultTransfer::fromCompilation(
            $result->getPhpCode(),
            $result->getSourceMap(),
        );
    }
}
