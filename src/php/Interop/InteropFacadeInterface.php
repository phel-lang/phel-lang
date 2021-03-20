<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Interop\ReadModel\Wrapper;

interface InteropFacadeInterface
{
    public function removeDestinationDir(): void;

    /**
     * @return array<string, list<FunctionToExport>>
     */
    public function getFunctionsToExport(): array;

    public function createFileFromWrapper(Wrapper $wrapper): void;

    /**
     * @param list<FunctionToExport> $functionsToExport
     */
    public function generateCompiledPhp(string $namespace, array $functionsToExport): Wrapper;
}
