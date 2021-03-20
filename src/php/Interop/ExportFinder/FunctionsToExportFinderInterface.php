<?php

declare(strict_types=1);

namespace Phel\Interop\ExportFinder;

use Phel\Command\Shared\Exceptions\ExtractorException;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Interop\ReadModel\FunctionToExport;

interface FunctionsToExportFinderInterface
{
    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws ExtractorException
     * @throws FileException
     *
     * @return array<string, list<FunctionToExport>>
     */
    public function findInPaths(): array;
}
