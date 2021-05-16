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
use Throwable;

final class TestCommand
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
        $this->io = $io;
        $this->runtimeFacade = $runtimeFacade;
        $this->compilerFacade = $compilerFacade;
        $this->defaultTestDirectories = $testDirectories;
    }

    public function addRuntimePath(string $namespacePrefix, array $path): self
    {
        $this->runtimeFacade->addPath($namespacePrefix, $path);

        return $this;
    }

    /**
     * @param list<string> $paths
     *
     * @return bool true if all tests were successful. False otherwise.
     */
    public function run(array $paths, ?TestCommandOptions $options = null): bool
    {
        try {
            return $this->evalNamespaces($paths, $options ?? TestCommandOptions::empty());
        } catch (CompilerException $e) {
            $this->io->writeLocatedException($e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->io->writeStackTrace($e);
        }

        return false;
    }

    /**
     * @param list<string> $paths
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws CannotFindAnyTestsException
     *
     * @return bool true if all tests were successful. False otherwise.
     */
    private function evalNamespaces(array $paths, TestCommandOptions $options): bool
    {
        $namespaces = $this->getNamespacesFromPaths($paths);

        if (empty($namespaces)) {
            throw CannotFindAnyTestsException::inPaths($paths);
        }

        $this->runtimeFacade->getRuntime()->loadNs('phel\test');

        return $this->compilerFacade->eval(sprintf(
            '(do (phel\test/run-tests %s %s) (successful?))',
            $options->asPhelHashMap(),
            $this->namespacesAsString($namespaces)
        ));
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
