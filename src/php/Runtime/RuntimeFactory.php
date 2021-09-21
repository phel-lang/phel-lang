<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractFactory;

final class RuntimeFactory extends AbstractFactory
{
    public function getRuntime(): RuntimeInterface
    {
        if (RuntimeSingleton::isInitialized()) {
            return RuntimeSingleton::getInstance();
        }

        return RuntimeSingleton::initialize();
    }
}
