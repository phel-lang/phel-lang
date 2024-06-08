<?php

declare(strict_types=1);

namespace Phel\Interop\Application;

use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Interop\Domain\DirectoryRemover\DirectoryRemoverInterface;
use Phel\Interop\Domain\ExportFinder\FunctionsToExportFinderInterface;
use Phel\Interop\Domain\FileCreator\FileCreatorInterface;
use Phel\Interop\Domain\Generator\WrapperGeneratorInterface;
use Phel\Interop\Domain\ReadModel\Wrapper;
use RuntimeException;

final readonly class ExportCodeGenerator
{
    public function __construct(
        private DirectoryRemoverInterface $directoryRemover,
        private WrapperGeneratorInterface $wrapperGenerator,
        private FunctionsToExportFinderInterface $functionsToExportFinder,
        private FileCreatorInterface $fileCreator,
    ) {
    }

    /**
     * @throws CompilerException
     * @throws RuntimeException
     *
     * @return list<Wrapper>
     */
    public function generateExportCode(): array
    {
        $this->directoryRemover->removeDir();
        $wrappers = $this->generateWrappers();

        foreach ($wrappers as $wrapper) {
            $this->fileCreator->createFromWrapper($wrapper);
        }

        return $wrappers;
    }

    /**
     * @return list<Wrapper>
     */
    private function generateWrappers(): array
    {
        $allFunctionsToExport = $this->functionsToExportFinder->findInPaths();
        $wrappers = [];

        foreach ($allFunctionsToExport as $ns => $functionsToExport) {
            $wrappers[] = $this->wrapperGenerator->generateCompiledPhp($ns, $functionsToExport);
        }

        return $wrappers;
    }
}
