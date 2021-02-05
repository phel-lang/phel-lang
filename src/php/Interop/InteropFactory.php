<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Generator\ExportFunctionGenerator;

final class InteropFactory implements InteropFactoryInterface
{
    public function createExportFunctionsGenerator(string $destinyDirectory): ExportFunctionGenerator
    {
        return new ExportFunctionGenerator($destinyDirectory);
    }
}
