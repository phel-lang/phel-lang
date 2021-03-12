<?php

declare(strict_types=1);

namespace Phel\Interop\Generator;

use Phel\Interop\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Interop\ReadModel\Wrapper;

final class WrapperGenerator implements WrapperGeneratorInterface
{
    private CompiledPhpClassBuilder $classBuilder;
    private WrapperRelativeFilenamePathBuilder $relativeFilenamePathBuilder;

    public function __construct(
        CompiledPhpClassBuilder $classBuilder,
        WrapperRelativeFilenamePathBuilder $relativeFilenamePathBuilder
    ) {
        $this->classBuilder = $classBuilder;
        $this->relativeFilenamePathBuilder = $relativeFilenamePathBuilder;
    }

    /**
     * @param list<FunctionToExport> $functionsToExport
     */
    public function generateCompiledPhp(string $phelNs, array $functionsToExport): Wrapper
    {
        $relativeFilenamePath = $this->relativeFilenamePathBuilder->build($phelNs);
        $compiledPhpClass = $this->classBuilder->build($phelNs, $functionsToExport);

        return new Wrapper($relativeFilenamePath, $compiledPhpClass);
    }
}
