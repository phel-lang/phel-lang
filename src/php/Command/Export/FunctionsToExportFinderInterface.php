<?php

declare(strict_types=1);

namespace Phel\Command\Export;

use Phel\Interop\ReadModel\FunctionToExport;

interface FunctionsToExportFinderInterface
{
    /**
     * @return array<string, list<FunctionToExport>>
     */
    public function findInPaths(): array;
}
