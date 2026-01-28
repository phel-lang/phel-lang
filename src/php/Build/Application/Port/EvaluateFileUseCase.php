<?php

declare(strict_types=1);

namespace Phel\Build\Application\Port;

use Phel\Build\Domain\Transfer\CompiledFileTransfer;

interface EvaluateFileUseCase
{
    /**
     * Evaluates a single Phel file without writing to disk.
     *
     * @param string $source The source file path
     */
    public function execute(string $source): CompiledFileTransfer;
}
