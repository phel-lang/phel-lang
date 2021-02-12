<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use Phel\Interop\Generator\WrapperGenerator;

final class InteropFactory implements InteropFactoryInterface
{
    private InteropConfigInterface $config;

    public function __construct(InteropConfigInterface $config)
    {
        $this->config = $config;
    }

    public function createWrapperGenerator(): WrapperGenerator
    {
        return new WrapperGenerator(
            new CompiledPhpClassBuilder($this->config->prefixNamespace(), new CompiledPhpMethodBuilder()),
            new WrapperRelativeFilenamePathBuilder()
        );
    }
}
