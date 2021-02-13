<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Run\Exceptions\CannotLoadNamespaceException;
use Phel\Command\Shared\Exceptions\ExtractorException;
use Phel\Command\Test\Exceptions\CannotFindAnyTestsException;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;

interface CommandFacadeInterface
{
    public function executeReplCommand(): void;

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws CannotLoadNamespaceException
     */
    public function executeRunCommand(string $fileOrPath): void;

    /**
     * @param list<string> $paths
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws CannotFindAnyTestsException
     */
    public function executeTestCommand(array $paths): void;

    /**
     * @param list<string> $paths
     */
    public function executeFormatCommand(array $paths): void;

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws ExtractorException
     * @throws FileException
     */
    public function executeExportCommand(): void;
}
