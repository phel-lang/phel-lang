<?php

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Runtime\RuntimeSingleton;

return RuntimeSingleton::initializeNew(new GlobalEnvironment());

