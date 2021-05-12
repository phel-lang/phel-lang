<?php

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Runtime\RuntimeSingleton;

require __DIR__ . '/../../../../../../vendor/autoload.php';

return RuntimeSingleton::initializeNew(new GlobalEnvironment());
