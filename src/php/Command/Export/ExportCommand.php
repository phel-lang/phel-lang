<?php

declare(strict_types=1);

namespace Phel\Command\Export;

use Phel\Command\Shared\CommandIoInterface;
use Phel\Interop\Generator\ExportFunctionGenerator;
use Phel\Interop\ReadModel\Wrapper;

final class ExportCommand
{
    public const COMMAND_NAME = 'export';

    private ExportFunctionGenerator $exportFunctionGenerator;
    private CommandIoInterface $io;
    private FunctionsToExportFinderInterface $exportFinder;

    public function __construct(
        ExportFunctionGenerator $exportFunctionGenerator,
        CommandIoInterface $io,
        FunctionsToExportFinderInterface $exportFinder
    ) {
        $this->exportFunctionGenerator = $exportFunctionGenerator;
        $this->io = $io;
        $this->exportFinder = $exportFinder;
    }

    /**
     * @param list<string> $paths
     */
    public function run(array $paths): void
    {
        $wrappers = [];
        foreach ($this->exportFinder->findInPaths($paths) as $ns => $functionsToExport) {
            $wrappers[] = $this->exportFunctionGenerator->generateWrapper($ns, ...$functionsToExport);
        }

        if (empty($wrappers)) {
            $this->io->writeln('No functions were found to be exported.');
            return;
        }

        $this->writeClassFiles(...$wrappers);
    }

    private function writeClassFiles(Wrapper ...$wrappers): void
    {
        $this->io->writeln('Exported namespaces:');

        foreach ($wrappers as $k => $wrapper) {
            $dir = dirname($wrapper->destinyPath());
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($wrapper->destinyPath(), $wrapper->compiledPhp());
            $this->io->writeln(sprintf('  %d) %s', $k + 1, $wrapper->destinyPath()));
        }
    }
}
