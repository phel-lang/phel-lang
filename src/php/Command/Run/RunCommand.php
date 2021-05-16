<?php

declare(strict_types=1);

namespace Phel\Command\Run;

use Phel\Command\Run\Exceptions\CannotLoadNamespaceException;
use Phel\Command\Shared\CommandIoInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Runtime\RuntimeFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class RunCommand extends Command
{
    public const COMMAND_NAME = 'run';

    private CommandIoInterface $io;
    private RuntimeFacadeInterface $runtimeFacade;

    public function __construct(
        CommandIoInterface $io,
        RuntimeFacadeInterface $runtimeFacade
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->io = $io;
        $this->runtimeFacade = $runtimeFacade;
    }

    protected function configure(): void
    {
        $this->setDescription('Runs a script.')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'The file path that you want to run.'
            );
    }

    public function addRuntimePath(string $namespacePrefix, array $path): self
    {
        $this->runtimeFacade->addPath($namespacePrefix, $path);

        return $this;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $fileOrPath */
        $fileOrPath = $input->getArgument('path');
        try {
            $this->loadNamespace($fileOrPath);
        } catch (CompilerException $e) {
            $this->io->writeLocatedException($e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->io->writeStackTrace($e);
        }

        return 0;
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws CannotLoadNamespaceException
     */
    private function loadNamespace(string $fileOrPath): void
    {
        $ns = file_exists($fileOrPath)
            ? $this->runtimeFacade->getNamespaceFromFile($fileOrPath)
            : $fileOrPath;

        $result = $this->runtimeFacade->getRuntime()->loadNs($ns);

        if (!$result) {
            throw CannotLoadNamespaceException::withName($ns);
        }
    }
}
