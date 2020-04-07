<?php

use Phel\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Runtime;

require __DIR__ .'/../../vendor/autoload.php';

Symbol::resetGen();
$globalEnv = new GlobalEnvironment();
$rt = new Runtime($globalEnv);
$rt->addPath('phel\\', [__DIR__ . '/../../src/phel']);
$rt->addPath('phel\\test\\', [__DIR__ . '/../../tests/phel/test']);
$rt->loadNs('phel\core');
$rt->loadNs('phel\test\core');