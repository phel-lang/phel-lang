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
//
//        $runtimePath = $this->getConfig()->getApplicationRootDir()
//            . DIRECTORY_SEPARATOR . 'vendor'
//            . DIRECTORY_SEPARATOR . 'PhelRuntime.php';
//
//        if (!file_exists($runtimePath)) {
//            throw PhelRuntimeException::couldNotBeLoadedFrom($runtimePath);
//        }
//
//        return require $runtimePath;
    }
}
