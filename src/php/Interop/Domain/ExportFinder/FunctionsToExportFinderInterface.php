<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\ExportFinder;

use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Interop\Domain\ReadModel\FunctionToExport;
use Phel\Transpiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Exceptions\CompilerException;

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
