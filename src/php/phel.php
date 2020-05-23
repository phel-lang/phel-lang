<?php

use Phel\Repl;

function getHelpText(): string {
    return <<<HELP
Usage: phel [command]

Commands:
    repl
        Start a Repl
    help
        Show this help message

HELP;
}

if (getcwd() === false) {
    fwrite(STDERR, 'Cannot get current working directory' . PHP_EOL);
    exit(1);
}

$vendorDir = 'vendor';
$currentDir = (string)getcwd() . DIRECTORY_SEPARATOR;

require $currentDir . $vendorDir . DIRECTORY_SEPARATOR . 'autoload.php';

if ($argc <= 1) {
    echo getHelpText();
    exit;
}

switch ($argv[1]) {
    case "repl":
        $repl = new Repl($currentDir);
        $repl->run();
        break;

    default:
        echo getHelpText();
        exit;
}