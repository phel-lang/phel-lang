<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Exceptions;

use Exception;

final class GlobalEnvironmentNotInitializedException extends Exception
{
    public function __construct()
    {
        parent::__construct('GlobalEnvironment must first be initialized. Call GlobalEnvironmentSingleton::initialize()');
    }
}
