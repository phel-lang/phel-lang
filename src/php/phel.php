<?php

declare(strict_types=1);

use Phel\Commands\ReplCommand;
use Phel\Commands\RunCommand;
use Phel\Commands\TestCommand;

function getHelpText(): string
{
    return <<<HELP
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
}

if (!getcwd()) {
    fwrite(STDERR, 'Cannot get current working directory' . PHP_EOL);
    exit(1);
}

$vendorDir = 'vendor';
$currentDir = getcwd() . DIRECTORY_SEPARATOR;
$autoloadPath = $currentDir . $vendorDir . DIRECTORY_SEPARATOR . 'autoload.php';


if (!file_exists($autoloadPath)) {
    throw new \RuntimeException("Can not load composer's autoload file: " . $autoloadPath);
}

require $autoloadPath;

if ($argc <= 1) {
    echo getHelpText();
    exit;
}

switch ($argv[1]) {
    case 'repl':
        $repl = new ReplCommand($currentDir);
        $repl->run();
        break;

    case 'run':
        if ($argc < 3) {
            echo "Please provide a filename or namespace as argument!\n";
            exit;
        }

        $runCmd = new RunCommand();
        $runCmd->run($currentDir, $argv[2]);
        break;

    case 'test':
        $testCmd = new TestCommand();
        $result = $testCmd->run($currentDir, array_slice($argv, 2));

        if ($result) {
            exit(0);
        } else {
            exit(1);
        }
        break;

    default:
        echo getHelpText();
        exit;
}
