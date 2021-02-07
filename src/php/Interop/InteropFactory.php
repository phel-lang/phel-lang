<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use Phel\Interop\Generator\WrapperGenerator;
use Phel\Interop\ReadModel\ExportConfig;

final class InteropFactory implements InteropFactoryInterface
{
    public function createWrapperGenerator(ExportConfig $exportConfig): WrapperGenerator
    {
        return new WrapperGenerator(
            $exportConfig->targetDir(),
            new CompiledPhpClassBuilder($exportConfig->prefixNamespace(), new CompiledPhpMethodBuilder()),
            new WrapperRelativeFilenamePathBuilder()
        );
    }
}
