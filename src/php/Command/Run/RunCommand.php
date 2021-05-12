<?php

declare(strict_types=1);

namespace Phel\Command\Run;

use Phel\Command\Run\Exceptions\CannotLoadNamespaceException;
use Phel\Command\Shared\CommandIoInterface;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Runtime\RuntimeFacadeInterface;
use Throwable;

final class RunCommand
{
    public const COMMAND_NAME = 'run';

    private CommandIoInterface $io;
    private RuntimeFacadeInterface $runtimeFacade;

    public function __construct(
        CommandIoInterface $io,
        RuntimeFacadeInterface $runtimeFacade
    ) {
        $this->io = $io;
        $this->runtimeFacade = $runtimeFacade;
    }

    public function addRuntimePath(string $namespacePrefix, array $path): self
    {
        $this->runtimeFacade->addPath($namespacePrefix, $path);

        return $this;
    }

    public function run(string $fileOrPath): void
    {
        try {
            $this->loadNamespace($fileOrPath);
        } catch (CompilerException $e) {
            $this->io->writeLocatedException($e->getNestedException(), $e->getCodeSnippet());
        } catch (Throwable $e) {
            $this->io->writeStackTrace($e);
        }
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
