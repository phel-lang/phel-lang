<?php

declare(strict_types=1);

namespace Phel\Interop\Generator;

use Phel\Interop\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Generator\Builder\WrapperDestinyBuilder;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Interop\ReadModel\Wrapper;

final class WrapperGenerator
{
    private string $destinyDirectory;
    private CompiledPhpClassBuilder $classBuilder;
    private WrapperDestinyBuilder $destinyBuilder;

    public function __construct(
        string $destinyDirectory,
        CompiledPhpClassBuilder $classBuilder,
        WrapperDestinyBuilder $destinyBuilder
    ) {
        $this->destinyDirectory = $destinyDirectory;
        $this->classBuilder = $classBuilder;
        $this->destinyBuilder = $destinyBuilder;
    }

    public function generateCompiledPhp(string $phelNs, FunctionToExport ...$functionsToExport): Wrapper
    {
        $destiny = $this->destinyBuilder->build($this->destinyDirectory, $phelNs);
        $compiledPhpClass = $this->classBuilder->build($phelNs, ...$functionsToExport);

        return new Wrapper($destiny, $compiledPhpClass);
    }
}
