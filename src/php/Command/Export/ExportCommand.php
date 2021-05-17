<?php

declare(strict_types=1);

namespace Phel\Command\Export;

use Phel\Command\Export\Exceptions\NoFunctionsFoundException;
use Phel\Command\Shared\CommandIoInterface;
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

    private CommandIoInterface $io;
    private RuntimeFacadeInterface $runtimeFacade;
    private InteropFacadeInterface $interopFacade;

    public function __construct(
        CommandIoInterface $io,
        RuntimeFacadeInterface $runtimeFacade,
        InteropFacadeInterface $interopFacade
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->io = $io;
        $this->runtimeFacade = $runtimeFacade;
        $this->interopFacade = $interopFacade;
    }

    protected function configure(): void
    {
        $this->setDescription('Export all definitions with the meta data `@{:export true}` as PHP classes.');
    }

    public function addRuntimePath(string $namespacePrefix, array $path): self
    {
        $this->runtimeFacade->addPath($namespacePrefix, $path);

        return $this;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $wrappers = $this->generateWrappers();
            $this->interopFacade->removeDestinationDir();
            $this->writeGeneratedWrappers($wrappers);

            return self::SUCCESS;
        } catch (NoFunctionsFoundException $e) {
            $this->io->writeln($e->getMessage());
        } catch (CompilerException $e) {
            $this->io->writeLocatedException($e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->io->writeStackTrace($e);
        }

        return self::FAILURE;
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

    /**
     * @param list<Wrapper> $wrappers
     */
    private function writeGeneratedWrappers(array $wrappers): void
    {
        $this->io->writeln('Exported namespaces:');

        foreach ($wrappers as $i => $wrapper) {
            $this->interopFacade->createFileFromWrapper($wrapper);
            $this->io->writeln(sprintf('  %d) %s', $i + 1, $wrapper->relativeFilenamePath()));
        }
    }
}
