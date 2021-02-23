<?php

declare(strict_types=1);

namespace Phel\Command\Run;

use Phel\Command\Run\Exceptions\CannotLoadNamespaceException;
use Phel\Command\Shared\CommandIoInterface;
use Phel\Command\Shared\NamespaceExtractorInterface;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Runtime\RuntimeInterface;
use Throwable;

final class RunCommand
{
    public const COMMAND_NAME = 'run';

    private RuntimeInterface $runtime;
    private CommandIoInterface $io;
    private NamespaceExtractorInterface $namespaceExtractor;

    public function __construct(
        RuntimeInterface $runtime,
        CommandIoInterface $io,
        NamespaceExtractorInterface $namespaceExtractor
    ) {
        $this->runtime = $runtime;
        $this->io = $io;
        $this->namespaceExtractor = $namespaceExtractor;
    }

    public function run(string $fileOrPath): void
    {
        try {
            $this->loadNamespace($fileOrPath);
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
            ? $this->namespaceExtractor->getNamespaceFromFile($fileOrPath)
            : $fileOrPath;

        $result = $this->runtime->loadNs($ns);

        if (!$result) {
            throw CannotLoadNamespaceException::withName($ns);
        }
    }
}
