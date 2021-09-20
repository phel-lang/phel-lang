<?php

declare(strict_types=1);

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Runtime\RuntimeSingleton;

$runtime = RuntimeSingleton::initializeNew(new GlobalEnvironment());
$runtime->addPath('phel\\', [__DIR__ . '/../../../../../../src/phel']);

return $runtime;
