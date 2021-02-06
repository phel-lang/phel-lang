<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Generator\WrapperGenerator;

interface InteropFactoryInterface
{
    public function createWrapperGenerator(string $destinyDirectory): WrapperGenerator;
}
