<?php

declare(strict_types=1);

namespace Phel\Interop\Command;

use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Interop\DirectoryRemover\DirectoryRemoverInterface;
use Phel\Interop\ExportFinder\FunctionsToExportFinderInterface;
use Phel\Interop\FileCreator\FileCreatorInterface;
use Phel\Interop\Generator\WrapperGeneratorInterface;
use Phel\Interop\ReadModel\Wrapper;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ExportCommand extends Command
{
    public const COMMAND_NAME = 'export';

    private DirectoryRemoverInterface $directoryRemover;
    private WrapperGeneratorInterface $wrapperGenerator;
    private FunctionsToExportFinderInterface $functionsToExportFinder;
    private FileCreatorInterface $fileCreator;
    private CommandFacadeInterface $commandFacade;

    public function __construct(
        DirectoryRemoverInterface $directoryRemover,
        WrapperGeneratorInterface $wrapperGenerator,
        FunctionsToExportFinderInterface $functionsToExportFinder,
        FileCreatorInterface $fileCreator,
        CommandFacadeInterface $commandFacade
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->directoryRemover = $directoryRemover;
        $this->wrapperGenerator = $wrapperGenerator;
        $this->functionsToExportFinder = $functionsToExportFinder;
        $this->fileCreator = $fileCreator;
        $this->commandFacade = $commandFacade;
    }

    protected function configure(): void
    {
        $this->setDescription('Export all definitions with the meta data `@{:export true}` as PHP classes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->createGeneratedWrappers($output);

            return self::SUCCESS;
        } catch (CompilerException $e) {
            $this->commandFacade->writeLocatedException($output, $e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->commandFacade->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    /**
     * @throws CompilerException
     * @throws RuntimeException
     */
    private function createGeneratedWrappers(OutputInterface $output): void
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
    public function generateWrappers(): array
    {
        $allFunctionsToExport = $this->functionsToExportFinder->findInPaths();
        $wrappers = [];

        foreach ($allFunctionsToExport as $ns => $functionsToExport) {
            $wrappers[] = $this->wrapperGenerator->generateCompiledPhp($ns, $functionsToExport);
        }

        return $wrappers;
    }
}
