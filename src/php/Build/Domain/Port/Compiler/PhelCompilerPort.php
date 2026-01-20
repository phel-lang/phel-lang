<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Port\Compiler;

use Phel\Build\Domain\Transfer\CompilationResultTransfer;

/**
 * Port interface for Phel code compilation.
 * Abstracts the Compiler module behind a Build-specific contract.
 */
interface PhelCompilerPort
{
    /**
     * Compiles Phel source code to PHP.
     *
     * @param string $phelCode         The Phel source code to compile
     * @param string $sourcePath       The path to the source file (for error reporting)
     * @param bool   $enableSourceMaps Whether to generate source maps
     */
    public function compile(
        string $phelCode,
        string $sourcePath,
        bool $enableSourceMaps,
    ): CompilationResultTransfer;
}
