<?php

declare(strict_types=1);

namespace Phel\Command\Test;

use Phel\Command\Shared\CommandIoInterface;
use Phel\Command\Shared\NamespaceExtractorInterface;
use Phel\Command\Test\Exceptions\CannotFindAnyTestsException;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\EvalCompilerInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Runtime\RuntimeInterface;
use Throwable;

final class TestCommand
{
    public const COMMAND_NAME = 'test';

    private string $projectRootDir;
    private RuntimeInterface $runtime;
    private CommandIoInterface $io;
    private NamespaceExtractorInterface $nsExtractor;
    private EvalCompilerInterface $evalCompiler;
    /** @var list<string> */
    private array $defaultDirectories;

    public function __construct(
        string $projectRootDir,
        RuntimeInterface $runtime,
        CommandIoInterface $io,
        NamespaceExtractorInterface $nsExtractor,
        EvalCompilerInterface $evalCompiler,
        array $defaultDirectories
    ) {
        $this->projectRootDir = $projectRootDir;
        $this->runtime = $runtime;
        $this->io = $io;
        $this->nsExtractor = $nsExtractor;
        $this->evalCompiler = $evalCompiler;
        $this->defaultDirectories = $defaultDirectories;
    }

    /**
     * @param list<string> $paths
     */
    public function run(array $paths): void
    {
        try {
            $this->evalNamespaces($paths);
        } catch (CompilerException $e) {
            $this->io->writeLocatedException($e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->io->writeStackTrace($e);
        }
    }

    /**
     * @param list<string> $paths
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws CannotFindAnyTestsException
     */
    private function evalNamespaces(array $paths): void
    {
        $namespaces = $this->getNamespacesFromPaths($paths);

        if (empty($namespaces)) {
            throw CannotFindAnyTestsException::inPaths($paths);
        }

        $this->runtime->loadNs('phel\test');
        $nsString = $this->namespacesAsString($namespaces);

        $this->evalCompiler->eval('(do (phel\test/run-tests ' . $nsString . ') (successful?))');
    }

    private function getNamespacesFromPaths(array $paths): array
    {
        if (empty($paths)) {
            return $this->nsExtractor->getNamespacesFromDirectories(
                $this->defaultDirectories,
                $this->projectRootDir
            );
        }

        return array_map(
            fn (string $filename): string => $this->nsExtractor->getNamespaceFromFile($filename),
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
