<?php

use Phel\Repl;

function getHelpText(): string
{
    return <<<HELP
Usage: phel [command]

Commands:
    repl
        Start a Repl
    help
        Show this help message

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

if ('repl' === $argv[1]) {
    (new Repl($currentDir))->run();
} else {
    echo getHelpText();
    exit;
}
