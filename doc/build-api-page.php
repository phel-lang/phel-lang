<?php

use Phel\GlobalEnvironment;
use Phel\Lang\Keyword;
use Phel\Lang\PhelVar;
use Phel\Lang\Table;
use Phel\Runtime;

require __DIR__ .'/../vendor/autoload.php';

$rt = Runtime::initialize();
$rt->addPath('phel\\', [__DIR__ . '/../src/phel']);
$rt->loadNs('phel\core');
$rt->loadNs('phel\http');

echo "+++\n";
echo "title = \"API\"\n";
echo "weight = 110\n";
echo "template = \"page-api.html\"\n";
echo "+++\n\n";

/** @var PhelVar $fn */
$normalizedData = [];
foreach ($GLOBALS["__phel"] as $ns => $functions) {
    $noramlizedNs = str_replace("phel\\", "", $ns);
    $moduleName = $noramlizedNs == "core" ? "" : $noramlizedNs . "/";
    foreach ($functions as $fnName => $fn) {
        $fullFnName = $moduleName . $fnName;

        $normalizedData[$fullFnName] = $GLOBALS["__phel_meta"][$ns][$fnName] ?? new Table();
    }
}

ksort($normalizedData);
foreach ($normalizedData as $fnName => $meta) {
    $doc = $meta[new Keyword('doc')] ?? "";
    $isPrivate = $meta[new Keyword("private")] ?? false;

    if (!$isPrivate) {
        echo "## `$fnName`\n\n";
        echo $doc;
        echo "\n\n";
    }
}