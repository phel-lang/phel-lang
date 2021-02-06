<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Generator\Builder\WrapperDestinyBuilder;
use Phel\Interop\Generator\WrapperGenerator;

final class InteropFactory implements InteropFactoryInterface
{
    public function createWrapperGenerator(string $destinyDirectory): WrapperGenerator
    {
        return new WrapperGenerator(
            $destinyDirectory,
            new CompiledPhpClassBuilder(new CompiledPhpMethodBuilder()),
            new WrapperDestinyBuilder()
        );
    }
}
