<?php

declare(strict_types=1);

namespace Phel\Command\Test;

use Phel\Command\Shared\CommandIoInterface;
use Phel\Command\Test\Exceptions\CannotFindAnyTestsException;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Runtime\RuntimeFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class TestCommand extends Command
{
    public const COMMAND_NAME = 'test';

    private CommandIoInterface $io;
    private RuntimeFacadeInterface $runtimeFacade;
    private CompilerFacadeInterface $compilerFacade;
    /** @var list<string> */
    private array $defaultTestDirectories;

    public function __construct(
        CommandIoInterface $io,
        RuntimeFacadeInterface $runtimeFacade,
        CompilerFacadeInterface $compilerFacade,
        array $testDirectories
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->io = $io;
        $this->runtimeFacade = $runtimeFacade;
        $this->compilerFacade = $compilerFacade;
        $this->defaultTestDirectories = $testDirectories;
    }

    protected function configure(): void
    {
        $this->setDescription('Tests the given files. If no filenames are provided all tests in the "tests" directory are executed.')
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The file paths that you want to format.',
                []
            );
    }

    public function addRuntimePath(string $namespacePrefix, array $path): self
    {
        $this->runtimeFacade->addPath($namespacePrefix, $path);

        return $this;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');
        try {
            $result = $this->evalNamespaces($paths);
            ($result)
                ? exit(self::SUCCESS)
                : exit(self::FAILURE);
        } catch (CompilerException $e) {
            $this->io->writeLocatedException($e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->io->writeStackTrace($e);
        }

        exit(self::FAILURE);
    }

    /**
     * @param list<string> $paths
     *
     * @throws CannotFindAnyTestsException
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return bool true if all tests were successful. False otherwise.
     */
    private function evalNamespaces(array $paths): bool
    {
        $namespaces = $this->getNamespacesFromPaths($paths);

        if (empty($namespaces)) {
            throw CannotFindAnyTestsException::inPaths($paths);
        }

        $this->runtimeFacade->getRuntime()->loadNs('phel\test');
        $nsString = $this->namespacesAsString($namespaces);

        return $this->compilerFacade->eval('(do (phel\test/run-tests ' . $nsString . ') (successful?))');
    }

    private function getNamespacesFromPaths(array $paths): array
    {
        if (empty($paths)) {
            return $this->runtimeFacade->getNamespacesFromDirectories($this->defaultTestDirectories);
        }

        return array_map(
            fn (string $filename): string => $this->runtimeFacade->getNamespaceFromFile($filename),
            $paths
        );
    }

    private function namespacesAsString(array $namespaces): string
    {
        return implode(' ', array_map(
            static fn (string $ns): string => "'" . $ns,
            $namespaces
        ));
    }
}
