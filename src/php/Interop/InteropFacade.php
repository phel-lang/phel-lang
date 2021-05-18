<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractFacade;
use Phel\Compiler\Exceptions\CompilerException;
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

    public function createFileFromWrapper(Wrapper $wrapper): void
    {
        $this->getFactory()
            ->createFileCreator()
            ->createFromWrapper($wrapper);
    }

    /**
     * @throws CompilerException
     *
     * @return list<Wrapper>
     */
    public function generateWrappers(): array
    {
        $wrapperGenerator = $this->getFactory()->createWrapperGenerator();

        $wrappers = [];
        foreach ($this->getFunctionsToExport() as $ns => $functionsToExport) {
            $wrappers[] = $wrapperGenerator->generateCompiledPhp($ns, $functionsToExport);
        }

        return $wrappers;
    }

    /**
     * @return array<string, list<FunctionToExport>>
     */
    private function getFunctionsToExport(): array
    {
        return $this->getFactory()
            ->createFunctionsToExportFinder()
            ->findInPaths();
    }
}
