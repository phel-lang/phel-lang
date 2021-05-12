<?php

declare(strict_types=1);

use Phel\Lang\Keyword;
use Phel\Lang\Table;
use Phel\Runtime\RuntimeSingleton;

require __DIR__ . '/../vendor/autoload.php';

$rt = RuntimeSingleton::initialize();
$rt->addPath('phel\\', [__DIR__ . '/../src/phel']);
$rt->loadNs('phel\core');
$rt->loadNs('phel\http');
$rt->loadNs('phel\test');

echo "+++\n";
echo "title = \"API\"\n";
echo "weight = 110\n";
echo "template = \"page-api.html\"\n";
echo "+++\n\n";

$normalizedData = [];
foreach ($GLOBALS['__phel'] as $ns => $functions) {
    $normalizedNs = str_replace('phel\\', '', $ns);
    $moduleName = $normalizedNs === 'core' ? '' : $normalizedNs . '/';
    foreach ($functions as $fnName => $fn) {
        $fullFnName = $moduleName . $fnName;

        $normalizedData[$fullFnName] = $GLOBALS['__phel_meta'][$ns][$fnName] ?? new Table();
    }
}

ksort($normalizedData);
foreach ($normalizedData as $fnName => $meta) {
    $doc = $meta[new Keyword('doc')] ?? '';
    $isPrivate = $meta[new Keyword('private')] ?? false;

    if (!$isPrivate) {
        echo "## `$fnName`\n\n";
        echo $doc;
        echo "\n\n";
    }
}
