<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractFacade;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Interop\ReadModel\Wrapper;

/**
 * @method InteropFactory getFactory()
 */
final class InteropFacade extends AbstractFacade implements InteropFacadeInterface
{
    public function removeDestinationDir(): void
    {
        $this->getFactory()
            ->createDirectoryRemover()
            ->removeDir();
    }

    /**
     * @return array<string, list<FunctionToExport>>
     */
    public function getFunctionsToExport(): array
    {
        return $this->getFactory()
            ->createFunctionsToExportFinder()
            ->findInPaths();
    }

    public function createFileFromWrapper(Wrapper $wrapper): void
    {
        $this->getFactory()
            ->createFileCreator()
            ->createFromWrapper($wrapper);
    }

    /**
     * @param list<FunctionToExport> $functionsToExport
     */
    public function generateCompiledPhp(string $namespace, array $functionsToExport): Wrapper
    {
        return $this->getFactory()
            ->createWrapperGenerator()
            ->generateCompiledPhp($namespace, $functionsToExport);
    }
}
