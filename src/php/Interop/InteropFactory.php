<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use Phel\Interop\Generator\WrapperGenerator;

final class InteropFactory implements InteropFactoryInterface
{
    public function createWrapperGenerator(string $destinationDir): WrapperGenerator
    {
        return new WrapperGenerator(
            $destinationDir,
            new CompiledPhpClassBuilder(new CompiledPhpMethodBuilder()),
            new WrapperRelativeFilenamePathBuilder()
        );
    }
}
