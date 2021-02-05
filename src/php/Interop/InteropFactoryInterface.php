<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Generator\ExportFunctionGenerator;

interface InteropFactoryInterface
{
    public function createExportFunctionsGenerator(string $destinyDirectory): ExportFunctionGenerator;
}
