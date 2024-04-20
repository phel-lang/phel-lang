<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\ExportFinder;

use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Interop\Domain\ReadModel\FunctionToExport;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\TrarnspiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Exceptions\TranspilerException;

interface FunctionsToExportFinderInterface
{
    /**
     *@throws TrarnspiledCodeIsMalformedException
     * @throws ExtractorException
     * @throws FileException
     * @throws TranspilerException
     *
     * @return array<string, list<FunctionToExport>>
     */
    public function findInPaths(): array;
}
