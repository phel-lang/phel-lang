<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Commands\Repl\ColorStyle;
use Phel\Commands\Repl\ReplCommandSystemIo;
use Phel\Commands\Run\RunCommandSystemIo;
use Phel\Commands\Utils\NamespaceExtractor;
use Phel\Compiler\EvalCompiler;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\GlobalEnvironment;
use Phel\Lexer;
use Phel\Reader;
use Phel\Runtime;

final class CommandFactory
{
    public static function createReplCommand(string $currentDir): ReplCommand
    {
        $globalEnv = new GlobalEnvironment();
        Runtime::initialize($globalEnv)->loadNs("phel\core");

        return new ReplCommand(
            new ReplCommandSystemIo($currentDir . '.phel-repl-history'),
            new EvalCompiler($globalEnv),
            TextExceptionPrinter::readableWithStyle(),
            ColorStyle::withStyles()
        );
    }

    public static function createRunCommand(string $currentDir): RunCommand
    {
        return new RunCommand(
            static::loadRuntime($currentDir),
            static::createNamespaceExtractor()
        );
    }

    public static function createTestCommand(string $currentDir): TestCommand
    {
        return new TestCommand(
            static::loadRuntime($currentDir),
            static::createNamespaceExtractor()
        );
    }

    private static function loadRuntime(string $currentDirectory): Runtime
    {
        $runtimePath = $currentDirectory
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'PhelRuntime.php';

        if (file_exists($runtimePath)) {
            return require $runtimePath;
        }

        throw new \RuntimeException('The Runtime could not be loaded from: ' . $runtimePath);
    }

    private static function createNamespaceExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            new Lexer(),
            new Reader(new GlobalEnvironment()),
            new RunCommandSystemIo()
        );
    }
}
