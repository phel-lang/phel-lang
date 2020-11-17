<?php

declare(strict_types=1);

namespace Phel;

use Exception;
use InvalidArgumentException;
use Phel\Command\CommandFactory;
use Phel\Command\ReplCommand;
use Phel\Command\RunCommand;
use Phel\Command\TestCommand;

final class Main
{
    public const HELP_TEXT = <<<HELP
Usage: phel [command]

Commands:
    repl
        Start a Repl.

    run <filename-or-namespace>
        Runs a script.

    test <filename> <filename> ...
        Tests the given files. If no filenames are provided all tests in the
        test directory are executed.

    help
        Show this help message.

HELP;

    private CommandFactory $commandFactory;

    public static function create(string $currentDir): self
    {
        if (!getcwd()) {
            throw new Exception('Cannot get current working directory');
        }

        static::requireAutoload($currentDir);

        return new self(new CommandFactory($currentDir));
    }

    private static function requireAutoload(string $currentDir): void
    {
        $autoloadPath = $currentDir . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        if (!file_exists($autoloadPath)) {
            throw new \RuntimeException("Can not load composer's autoload file: " . $autoloadPath);
        }

        require $autoloadPath;
    }

    private function __construct(CommandFactory $commandFactory)
    {
        $this->commandFactory = $commandFactory;
    }

    public function run(string $commandName, array $arguments = []): void
    {
        switch ($commandName) {
            case ReplCommand::COMMAND_NAME:
                $this->executeReplCommand();
                break;
            case RunCommand::COMMAND_NAME:
                $this->executeRunCommand($arguments);
                break;
            case TestCommand::COMMAND_NAME:
                $this->executeTestCommand($arguments);
                break;
            default:
                $this->renderHelp();
        }
    }

    private function executeReplCommand(): void
    {
        $replCommand = $this->commandFactory->createReplCommand();
        $replCommand->run();
    }

    private function executeRunCommand(array $arguments): void
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('Please provide a filename or namespace as argument!');
        }

        $runCommand = $this->commandFactory->createRunCommand();
        $runCommand->run($arguments[0]);
    }

    private function executeTestCommand(array $arguments): void
    {
        $testCommand = $this->commandFactory->createTestCommand();
        $result = $testCommand->run($arguments);
        ($result) ? exit(0) : exit(1);
    }

    private function renderHelp(): void
    {
        echo self::HELP_TEXT;
    }
}
