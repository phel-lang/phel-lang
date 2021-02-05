<?php

declare(strict_types=1);

namespace Phel\Command\Export;

use Phel\Interop\Generator\FunctionToExport;

interface FunctionsToExportFinderInterface
{
    /**
     * @param list<string> $paths
     *
     * @return array<string, list<FunctionToExport>>
     */
    public function findInPaths(array $paths): array;
}
