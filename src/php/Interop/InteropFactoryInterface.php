<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Generator\WrapperGenerator;
use Phel\Interop\ReadModel\ExportConfig;

interface InteropFactoryInterface
{
    public function createWrapperGenerator(ExportConfig $exportConfig): WrapperGenerator;
}
