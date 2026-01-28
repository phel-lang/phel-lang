<?php

declare(strict_types=1);

namespace Phel\Build\Application\Port;

use Phel\Build\Domain\Transfer\CompiledFileTransfer;

interface CompileFileUseCase
{
    /**
     * Compiles a single Phel file.
     *
     * @param string $source          The source file path
     * @param string $destination     The target file path
     * @param bool   $enableSourceMap Whether to generate source maps
     */
    public function execute(string $source, string $destination, bool $enableSourceMap = true): CompiledFileTransfer;
}
