<?php

declare(strict_types=1);

use Phel\Commands\Repl;
use Phel\Commands\Run;

function getHelpText(): string
{
    return <<<HELP
Usage: phel [command]

Commands:
    repl
        Start a Repl.
    run <filename-or-namespace>
        Runs a script.
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

require $currentDir . $vendorDir . DIRECTORY_SEPARATOR . 'autoload.php';

if ($argc <= 1) {
    echo getHelpText();
    exit;
}

switch ($argv[1]) {
    case 'repl':
        $repl = new Repl($currentDir);
        $repl->run();
        break;

    case 'run':
        if ($argc < 3) {
            echo "Please provide a filename or namespace as argument!\n";
            exit;
        }

        $runCmd = new Run();
        $runCmd->run($currentDir, $argv[2]);
        break;

    default:
        echo getHelpText();
        exit;
}
