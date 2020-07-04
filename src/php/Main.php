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
    private array $arguments;

    public function __construct(string $currentDir, array $arguments = [])
    {
        if (!getcwd()) {
            fwrite(STDERR, 'Cannot get current working directory' . PHP_EOL);
            exit(1);
        }

        if (count($arguments) <= 1) {
            $this->renderHelpAndExit();
        }

        $this->requireAutoload($currentDir);
        $this->currentDir = $currentDir;
        $this->arguments = $arguments;
    }

    private function requireAutoload(string $currentDir): void
    {
        $autoloadPath = $currentDir . self::VENDOR_DIR . DIRECTORY_SEPARATOR . 'autoload.php';

        if (!file_exists($autoloadPath)) {
            throw new \RuntimeException("Can not load composer's autoload file: " . $autoloadPath);
        }

        require $autoloadPath;
    }

    public function run(): void
    {
        switch ($this->arguments[1]) {
            case 'repl':
                $this->executeReplCommand();
                break;
            case 'run':
                $this->executeRunCommand();
                break;
            case 'test':
                $this->executeTestCommand();
                break;
            default:
                $this->renderHelpAndExit();
        }
    }

    private function executeReplCommand(): void
    {
        $replCommand = new ReplCommand($this->currentDir);
        $replCommand->run();
    }

    private function executeRunCommand(): void
    {
        if (count($this->arguments) < 3) {
            echo "Please provide a filename or namespace as argument!\n";
            exit;
        }

        $runCommand = new RunCommand();
        $runCommand->run($this->currentDir, $this->arguments[2]);
    }

    private function executeTestCommand(): void
    {
        $testCommand = new TestCommand();
        $result = $testCommand->run($this->currentDir, array_slice($this->arguments, 2));

        if ($result) {
            exit(0);
        }

        exit(1);
    }

    private function renderHelpAndExit(): void
    {
        echo self::HELP_TEXT;
        exit;
    }
}

$currentDir = getcwd() . DIRECTORY_SEPARATOR;
$entryPoint = new Main($currentDir, $argv);
$entryPoint->run();
