<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Repl\ColorStyle;
use Phel\Command\Repl\ReplCommandSystemIo;
use Phel\Command\Shared\CommandSystemIo;
use Phel\Command\Shared\NamespaceExtractor;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\GlobalEnvironmentInterface;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\RuntimeInterface;

final class CommandFactory
{
    private string $currentDir;
    private CompilerFactory $compilerFactory;

    public function __construct(string $currentDir, CompilerFactory $compilerFactory)
    {
        $this->currentDir = $currentDir;
        $this->compilerFactory = $compilerFactory;
    }

    public function createReplCommand(GlobalEnvironment $globalEnv): ReplCommand
    {
        return new ReplCommand(
            new ReplCommandSystemIo($this->currentDir . '.phel-repl-history'),
            $this->compilerFactory->createEvalCompiler($globalEnv),
            TextExceptionPrinter::readableWithStyle(),
            ColorStyle::withStyles()
        );
    }

    public function createRunCommand(RuntimeInterface $runtime): RunCommand
    {
        return new RunCommand(
            $runtime,
            $this->createNamespaceExtractor($runtime->getEnv())
        );
    }

    public function createTestCommand(RuntimeInterface $runtime): TestCommand
    {
        return new TestCommand(
            $this->currentDir,
            $runtime,
            $this->createNamespaceExtractor($runtime->getEnv()),
            $this->compilerFactory->createEvalCompiler($runtime->getEnv())
        );
    }

    public function createNamespaceExtractor(GlobalEnvironmentInterface $globalEnv): NamespaceExtractor
    {
        return new NamespaceExtractor(
            $this->compilerFactory->createLexer(),
            $this->compilerFactory->createReader($globalEnv),
            new CommandSystemIo()
        );
    }
}
