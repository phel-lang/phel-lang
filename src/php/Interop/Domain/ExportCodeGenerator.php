<?php

declare(strict_types=1);

namespace Phel\Interop\Domain;

use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Interop\Domain\DirectoryRemover\DirectoryRemoverInterface;
use Phel\Interop\Domain\ExportFinder\FunctionsToExportFinderInterface;
use Phel\Interop\Domain\FileCreator\FileCreatorInterface;
use Phel\Interop\Domain\Generator\WrapperGeneratorInterface;
use Phel\Interop\Domain\ReadModel\Wrapper;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

final class ExportCodeGenerator
{
    private DirectoryRemoverInterface $directoryRemover;
    private WrapperGeneratorInterface $wrapperGenerator;
    private FunctionsToExportFinderInterface $functionsToExportFinder;
    private FileCreatorInterface $fileCreator;

    public function __construct(
        DirectoryRemoverInterface $directoryRemover,
        WrapperGeneratorInterface $wrapperGenerator,
        FunctionsToExportFinderInterface $functionsToExportFinder,
        FileCreatorInterface $fileCreator,
    ) {
        $this->directoryRemover = $directoryRemover;
        $this->wrapperGenerator = $wrapperGenerator;
        $this->functionsToExportFinder = $functionsToExportFinder;
        $this->fileCreator = $fileCreator;
    }

    /**
     * @throws CompilerException
     * @throws RuntimeException
     */
    public function generateExportCode(OutputInterface $output): void
    {
        $this->directoryRemover->removeDir();
        $output->writeln('Exported namespaces:');
        $wrappers = $this->generateWrappers();

        if (empty($wrappers)) {
            $output->writeln('No functions were found to be exported');
        }

        foreach ($wrappers as $i => $wrapper) {
            $this->fileCreator->createFromWrapper($wrapper);
            $output->writeln(sprintf('  %d) %s', $i + 1, $wrapper->relativeFilenamePath()));
        }
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
