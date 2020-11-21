<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Repl\ColorStyle;
use Phel\Command\Repl\ReplCommandSystemIo;
use Phel\Command\Shared\CommandSystemIo;
use Phel\Command\Shared\NamespaceExtractor;
use Phel\Compiler\EvalCompiler;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\GlobalEnvironment;
use Phel\Compiler\Lexer;
use Phel\Compiler\Reader;
use Phel\Runtime;

final class CommandFactory
{
    private string $currentDir;

    public function __construct(string $currentDir)
    {
        $this->currentDir = $currentDir;
    }

    public function createReplCommand(): ReplCommand
    {
        $globalEnv = new GlobalEnvironment();
        Runtime::initialize($globalEnv)->loadNs('phel\core');

        return new ReplCommand(
            new ReplCommandSystemIo($this->currentDir . '.phel-repl-history'),
            new EvalCompiler($globalEnv),
            TextExceptionPrinter::readableWithStyle(),
            ColorStyle::withStyles()
        );
    }

    public function createRunCommand(): RunCommand
    {
        return new RunCommand(
            $this->loadVendorPhelRuntime(),
            $this->createNamespaceExtractor()
        );
    }

    public function createTestCommand(): TestCommand
    {
        $runtime = $this->loadVendorPhelRuntime();

        return new TestCommand(
            $this->currentDir,
            $runtime,
            $this->createNamespaceExtractor(),
            new EvalCompiler($runtime->getEnv())
        );
    }

    public function createNamespaceExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            new Lexer(),
            new Reader(new GlobalEnvironment()),
            new CommandSystemIo()
        );
    }

    private function loadVendorPhelRuntime(): Runtime
    {
        $runtimePath = $this->currentDir
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'PhelRuntime.php';

        if (file_exists($runtimePath)) {
            return require $runtimePath;
        }

        throw new \RuntimeException('The Runtime could not be loaded from: ' . $runtimePath);
    }
}
