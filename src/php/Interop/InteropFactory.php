<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use Phel\Interop\Generator\WrapperGenerator;

final class InteropFactory implements InteropFactoryInterface
{
    private string $targetFolder;
    private string $prefixNamespace;

    public function __construct(string $targetFolder, string $prefixNamespace)
    {
        $this->targetFolder = $targetFolder;
        $this->prefixNamespace = $prefixNamespace;
    }

    public function createWrapperGenerator(string $destinationDir): WrapperGenerator
    {
        return new WrapperGenerator(
            $this->targetFolder,
            new CompiledPhpClassBuilder($this->prefixNamespace, new CompiledPhpMethodBuilder()),
            new WrapperRelativeFilenamePathBuilder()
        );
    }
}
