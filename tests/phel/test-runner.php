<?php

declare(strict_types=1);

use Phel\Runtime;

require __DIR__ . '/../../vendor/autoload.php';

$rt = Runtime::initialize();
$rt->addPath('phel\\', [__DIR__ . '/../../src/phel']);
$rt->addPath('phel\\test\\', [__DIR__ . '/../../tests/phel/test']);
$rt->loadNs('phel\core');
$rt->loadNs('phel\test\core');
$rt->loadNs('phel\test\http');
