<?php

declare(strict_types=1);

namespace Phel;

use InvalidArgumentException;
use Phel\Command\CommandFactoryInterface;
use Phel\Command\ReplCommand;
use Phel\Command\RunCommand;
use Phel\Command\TestCommand;
use Phel\Compiler\GlobalEnvironment;
use Phel\Exceptions\ExitException;
use Phel\Runtime\RuntimeFactory;
use Phel\Runtime\RuntimeInterface;
use RuntimeException;

final class PhelFacade
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
    private const SUCCESS_CODE = 0;
    private const FAILED_CODE = 1;

    private string $currentDir;
    private CommandFactoryInterface $commandFactory;

    public function __construct(
        string $currentDir,
        CommandFactoryInterface $commandFactory
    ) {
        $this->currentDir = $currentDir;
        $this->commandFactory = $commandFactory;
    }

    /**
     * @throws ExitException
     */
    public function runCommand(string $commandName, array $arguments = []): void
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
                throw new ExitException(self::HELP_TEXT);
        }
    }

    private function executeReplCommand(): void
    {
        $globalEnv = new GlobalEnvironment();
        RuntimeFactory::initialize($globalEnv)->loadNs('phel\core');

        $this->commandFactory
            ->createReplCommand($globalEnv)
            ->run();
    }

    private function executeRunCommand(array $arguments): void
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('Please provide a filename or namespace as argument!');
        }

        $this->commandFactory
            ->createRunCommand($this->loadVendorPhelRuntime())
            ->run($arguments[0]);
    }

    private function executeTestCommand(array $arguments): void
    {
        $result = $this->commandFactory
            ->createTestCommand($this->loadVendorPhelRuntime())
            ->run($arguments);

        ($result)
            ? exit(self::SUCCESS_CODE)
            : exit(self::FAILED_CODE);
    }

    private function loadVendorPhelRuntime(): RuntimeInterface
    {
        $runtimePath = $this->currentDir
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'PhelRuntime.php';

        if (!file_exists($runtimePath)) {
            throw new RuntimeException('The Runtime could not be loaded from: ' . $runtimePath);
        }

        return require $runtimePath;
    }
}
