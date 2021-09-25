<?php

declare(strict_types=1);

namespace Phel\Command\Export;

use Phel\Command\Shared\CommandExceptionWriterInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Interop\InteropFacadeInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ExportCommand extends Command
{
    public const COMMAND_NAME = 'export';

    private CommandExceptionWriterInterface $exceptionWriter;
    private InteropFacadeInterface $interopFacade;

    public function __construct(
        CommandExceptionWriterInterface $exceptionWriter,
        InteropFacadeInterface $interopFacade
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->exceptionWriter = $exceptionWriter;
        $this->interopFacade = $interopFacade;
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
            $this->exceptionWriter->writeLocatedException($output, $e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->exceptionWriter->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    /**
     * @throws CompilerException
     * @throws RuntimeException
     */
    private function createGeneratedWrappers(OutputInterface $output): void
    {
        $this->interopFacade->removeDestinationDir();
        $output->writeln('Exported namespaces:');
        $wrappers = $this->interopFacade->generateWrappers();

        if (empty($wrappers)) {
            $output->writeln('No functions were found to be exported');
        }

        foreach ($wrappers as $i => $wrapper) {
            $this->interopFacade->createFileFromWrapper($wrapper);
            $output->writeln(sprintf('  %d) %s', $i + 1, $wrapper->relativeFilenamePath()));
        }
    }
}
