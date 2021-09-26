<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractFactory;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Runtime\Exceptions\PhelRuntimeException;

/**
 * @method RuntimeConfig getConfig()
 */
final class RuntimeFactory extends AbstractFactory
{
    /**
     * @throws PhelRuntimeException
     */
    public function getRuntime(): RuntimeInterface
    {
        if (!RuntimeSingleton::isInitialized()) {
            return RuntimeSingleton::initializeNew(new GlobalEnvironment());
        }
        return RuntimeSingleton::getInstance();
    }
}
