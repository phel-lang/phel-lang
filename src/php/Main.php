<?php

declare(strict_types=1);

namespace Phel;

use Phel\Commands\ReplCommand;
use Phel\Commands\RunCommand;
use Phel\Commands\TestCommand;

final class Main
{
    private const VENDOR_DIR = 'vendor';
    private const HELP_TEXT = <<<HELP
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

    private string $currentDir;
    private string $commandName;
    private array $arguments;

    public static function create(string $currentDir, string $commandName, array $arguments = []): Main
    {
        if (!getcwd()) {
            fwrite(STDERR, 'Cannot get current working directory' . PHP_EOL);
            exit(1);
        }

        static::requireAutoload($currentDir);

        return new self($currentDir, $commandName, $arguments);
    }

    public static function renderHelp(): void
    {
        echo self::HELP_TEXT;
    }

    /** @psalm-pure */
    private static function requireAutoload(string $currentDir): void
    {
        $autoloadPath = $currentDir . self::VENDOR_DIR . DIRECTORY_SEPARATOR . 'autoload.php';

        if (!file_exists($autoloadPath)) {
            throw new \RuntimeException("Can not load composer's autoload file: " . $autoloadPath);
        }

        require $autoloadPath;
    }

    private function __construct(string $currentDir, string $commandName, array $arguments)
    {
        $this->currentDir = $currentDir;
        $this->commandName = $commandName;
        $this->arguments = $arguments;
    }

    public function run(): void
    {
        switch ($this->commandName) {
            case ReplCommand::NAME:
                $this->executeReplCommand();
                break;
            case RunCommand::NAME:
                $this->executeRunCommand();
                break;
            case TestCommand::NAME:
                $this->executeTestCommand();
                break;
            default:
                static::renderHelp();
        }
    }

    private function executeReplCommand(): void
    {
        $replCommand = new ReplCommand($this->currentDir);
        $replCommand->run();
    }

    private function executeRunCommand(): void
    {
        if (empty($this->arguments)) {
            echo "Please provide a filename or namespace as argument!\n";
            exit;
        }

        $runCommand = new RunCommand();
        $runCommand->run($this->currentDir, $this->arguments[0]);
    }

    private function executeTestCommand(): void
    {
        $testCommand = new TestCommand();
        $result = $testCommand->run($this->currentDir, $this->arguments);

        if ($result) {
            exit(0);
        }

        exit(1);
    }
}
