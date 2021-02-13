<?php

declare(strict_types=1);

namespace Phel\Command\Export;

use Phel\Command\Shared\CommandIoInterface;
use Phel\Interop\Generator\WrapperGeneratorInterface;
use Phel\Interop\ReadModel\Wrapper;

final class ExportCommand
{
    public const COMMAND_NAME = 'export';

    private WrapperGeneratorInterface $wrapperGenerator;
    private CommandIoInterface $io;
    private FunctionsToExportFinderInterface $functionsToExportFinder;
    private DirectoryRemoverInterface $directoryRemover;
    private string $destinationDir;

    public function __construct(
        WrapperGeneratorInterface $wrapperGenerator,
        CommandIoInterface $io,
        FunctionsToExportFinderInterface $functionsToExportFinder,
        DirectoryRemoverInterface $directoryRemover,
        string $destinationDir
    ) {
        $this->wrapperGenerator = $wrapperGenerator;
        $this->io = $io;
        $this->functionsToExportFinder = $functionsToExportFinder;
        $this->directoryRemover = $directoryRemover;
        $this->destinationDir = $destinationDir;
    }

    public function run(): void
    {
        $wrappers = [];
        foreach ($this->functionsToExportFinder->findInPaths() as $ns => $functionsToExport) {
            $wrappers[] = $this->wrapperGenerator->generateCompiledPhp($ns, $functionsToExport);
        }

        $this->directoryRemover->removeDir($this->destinationDir);

        if (empty($wrappers)) {
            $this->io->writeln('No functions were found to be exported.');
            return;
        }

        $this->writeGeneratedWrappers($wrappers);
    }

    /**
     * @param list<Wrapper> $wrappers
     */
    private function writeGeneratedWrappers(array $wrappers): void
    {
        $this->io->writeln('Exported namespaces:');

        foreach ($wrappers as $i => $wrapper) {
            $wrapperPath = $this->destinationDir . '/' . $wrapper->relativeFilenamePath();
            $dir = dirname($wrapperPath);

            if (!is_dir($dir)) {
                $this->io->createDirectory($dir);
            }

            $this->io->filePutContents($wrapperPath, $wrapper->compiledPhp());
            $this->io->writeln(sprintf('  %d) %s', $i + 1, $wrapper->relativeFilenamePath()));
        }
    }
}
