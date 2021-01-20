<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Repl\ColorStyle;
use Phel\Command\Repl\ReplCommandSystemIo;
use Phel\Command\Shared\CommandSystemIo;
use Phel\Command\Shared\NamespaceExtractor;
use Phel\Command\Shared\NamespaceExtractorInterface;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\CompilerFactoryInterface;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\Formatter\Formatter;
use Phel\Runtime\RuntimeInterface;

final class CommandFactory implements CommandFactoryInterface
{
    private string $currentDir;
    private CompilerFactoryInterface $compilerFactory;

    public function __construct(string $currentDir, CompilerFactoryInterface $compilerFactory)
    {
        $this->currentDir = $currentDir;
        $this->compilerFactory = $compilerFactory;
    }

    public function createReplCommand(RuntimeInterface $runtime): ReplCommand
    {
        $runtime->loadFileIntoNamespace('user', __DIR__ . '/Repl/startup.phel');

        return new ReplCommand(
            new ReplCommandSystemIo($this->currentDir . '.phel-repl-history'),
            $this->compilerFactory->createEvalCompiler($runtime->getEnv()),
            TextExceptionPrinter::create(),
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

    public function createFormatCommand(): FormatCommand
    {
        return new FormatCommand(
            $this->currentDir,
            new Formatter(
                $this->compilerFactory->createLexer(),
                $this->compilerFactory->createParser(),
            )
        );
    }

    private function createNamespaceExtractor(GlobalEnvironmentInterface $globalEnv): NamespaceExtractorInterface
    {
        return new NamespaceExtractor(
            $this->compilerFactory->createLexer(),
            $this->compilerFactory->createParser(),
            $this->compilerFactory->createReader($globalEnv),
            new CommandSystemIo()
        );
    }
}
