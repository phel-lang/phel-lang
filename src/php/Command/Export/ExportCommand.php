<?php

declare(strict_types=1);

namespace Phel\Command\Export;

use Phel\Command\Export\Exceptions\NoFunctionsFoundException;
use Phel\Command\Shared\CommandExceptionWriterInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Interop\InteropFacadeInterface;
use Phel\Interop\ReadModel\Wrapper;
use Phel\Runtime\RuntimeFacadeInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ExportCommand extends Command
{
    public const COMMAND_NAME = 'export';

    private CommandExceptionWriterInterface $exceptionWriter;
    private RuntimeFacadeInterface $runtimeFacade;
    private InteropFacadeInterface $interopFacade;

    public function __construct(
        CommandExceptionWriterInterface $exceptionWriter,
        RuntimeFacadeInterface $runtimeFacade,
        InteropFacadeInterface $interopFacade
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->exceptionWriter = $exceptionWriter;
        $this->runtimeFacade = $runtimeFacade;
        $this->interopFacade = $interopFacade;
    }

    public function addRuntimePath(string $namespacePrefix, array $path): self
    {
        $this->runtimeFacade->addPath($namespacePrefix, $path);

        return $this;
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
        } catch (NoFunctionsFoundException $e) {
            $output->writeln($e->getMessage());

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
     * @throws NoFunctionsFoundException
     */
    private function createGeneratedWrappers(OutputInterface $output): void
    {
        $this->interopFacade->removeDestinationDir();
        $output->writeln('Exported namespaces:');

        foreach ($this->generateWrappers() as $i => $wrapper) {
            $this->interopFacade->createFileFromWrapper($wrapper);
            $output->writeln(sprintf('  %d) %s', $i + 1, $wrapper->relativeFilenamePath()));
        }
    }

    /**
     * @throws RuntimeException
     * @throws CompilerException
     *
     * @return list<Wrapper>
     */
    private function generateWrappers(): array
    {
        $wrappers = [];
        foreach ($this->interopFacade->getFunctionsToExport() as $ns => $functionsToExport) {
            $wrappers[] = $this->interopFacade->generateCompiledPhp($ns, $functionsToExport);
        }
        if (empty($wrappers)) {
            throw new NoFunctionsFoundException();
        }

        return $wrappers;
    }
}
