<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

final class GlobalEnvironmentManager
{
    public function initialize(): void
    {
        GlobalEnvironmentSingleton::ensureInitialized();
    }

    public function reset(): void
    {
        GlobalEnvironmentSingleton::reset();
    }

    public function isInitialized(): bool
    {
        return GlobalEnvironmentSingleton::isInitialized();
    }

    public function getInstance(): GlobalEnvironmentInterface
    {
        return GlobalEnvironmentSingleton::getInstance();
    }

    public function initializeNew(): GlobalEnvironmentInterface
    {
        return GlobalEnvironmentSingleton::initializeNew();
    }

    public function setInstance(GlobalEnvironmentInterface $env): void
    {
        GlobalEnvironmentSingleton::setInstance($env);
    }
}
