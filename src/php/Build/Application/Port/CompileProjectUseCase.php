<?php

declare(strict_types=1);

namespace Phel\Build\Application\Port;

use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Transfer\CompiledFileTransfer;

interface CompileProjectUseCase
{
    /**
     * Compiles an entire Phel project based on the provided build options.
     *
     * @return list<CompiledFileTransfer>
     */
    public function execute(BuildOptions $options): array;
}
