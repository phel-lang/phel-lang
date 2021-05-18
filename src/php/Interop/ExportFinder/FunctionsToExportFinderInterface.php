<?php

declare(strict_types=1);

namespace Phel\Interop\ExportFinder;

use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Runtime\Extractor\ExtractorException;

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
