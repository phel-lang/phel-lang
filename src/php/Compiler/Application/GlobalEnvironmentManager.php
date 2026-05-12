<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentManagerInterface;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentRegistry;
use Phel\Compiler\Domain\Evaluator\RequireEvaluator;

final class GlobalEnvironmentManager implements GlobalEnvironmentManagerInterface
{
    public function initialize(): GlobalEnvironmentInterface
    {
        $existing = GlobalEnvironmentRegistry::get();
        if ($existing instanceof GlobalEnvironmentInterface) {
            return $existing;
        }

        return $this->initializeNew();
    }

    public function reset(): void
    {
        GlobalEnvironmentRegistry::set(null);
    }

    public function isInitialized(): bool
    {
        return GlobalEnvironmentRegistry::has();
    }

    public function getInstance(): GlobalEnvironmentInterface
    {
        $existing = GlobalEnvironmentRegistry::get();
        if ($existing instanceof GlobalEnvironmentInterface) {
            return $existing;
        }

        $env = new GlobalEnvironment();
        GlobalEnvironmentRegistry::set($env);

        return $env;
    }

    public function initializeNew(): GlobalEnvironmentInterface
    {
        Phel::clear();
        RequireEvaluator::clearCache();
        $env = new GlobalEnvironment();
        GlobalEnvironmentRegistry::set($env);

        return $env;
    }

    public function setInstance(GlobalEnvironmentInterface $env): void
    {
        GlobalEnvironmentRegistry::set($env);
    }
}
