<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Commands\Repl\ColorStyle;
use Phel\Commands\Repl\ReplCommandSystemIo;
use Phel\Commands\Utils\NamespaceExtractor;
use Phel\Compiler\EvalCompiler;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\GlobalEnvironment;
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
        Runtime::initialize($globalEnv)->loadNs("phel\core");

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
            $this->loadRuntime(),
            NamespaceExtractor::create()
        );
    }

    public function createTestCommand(): TestCommand
    {
        return new TestCommand(
            $this->currentDir,
            $this->loadRuntime(),
            NamespaceExtractor::create()
        );
    }

    private function loadRuntime(): Runtime
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
