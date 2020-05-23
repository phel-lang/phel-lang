<?php

use Phel\GlobalEnvironment;
use Phel\Lang\Keyword;
use Phel\Lang\PhelVar;
use Phel\Lang\Table;
use Phel\Runtime;

require __DIR__ .'/../vendor/autoload.php';

$globalEnv = new GlobalEnvironment();
$rt = new Runtime($globalEnv);
$rt->addPath('phel\\', [__DIR__ . '/../src/phel']);
$rt->loadNs('phel\core');

echo "+++\n";
echo "title = \"API\"\n";
echo "weight = 110\n";
echo "template = \"page-api.html\"\n";
echo "+++\n\n";

/** @var PhelVar $fn */
$ns = $GLOBALS["__phel"]["phel\\core"];
ksort($ns);
foreach ($ns as $fnName => $fn) {
    $meta = $GLOBALS["__phel_meta"]["phel\\core"][$fnName] ?? new Table();
    $doc = $meta[new Keyword('doc')] ?? "";
    $isPrivate = $meta[new Keyword("private")] ?? false;

    if (!$isPrivate) {
        echo "## `$fnName`\n\n";
        echo $doc;
        echo "\n\n";
    }
}