<?php

declare(strict_types=1);

namespace Phel\Compiler\Infrastructure;

use Phel;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentRegistry;
use Phel\Compiler\Domain\Analyzer\Exceptions\GlobalEnvironmentAlreadyInitializedException;
use Phel\Compiler\Domain\Evaluator\RequireEvaluator;

/**
 * Static accessor preserved for the compiled-PHP ABI: the emitter writes
 * literal `\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()`
 * calls into generated code, so cached `.phel` artefacts depend on this
 * exact FQN. State now lives in `Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentRegistry`;
 * each method here is a thin forwarder.
 *
 * For new code prefer injecting `GlobalEnvironmentManagerInterface`.
 */
final class GlobalEnvironmentSingleton
{
    public static function reset(): void
    {
        GlobalEnvironmentRegistry::set(null);
    }

    public static function isInitialized(): bool
    {
        return GlobalEnvironmentRegistry::has();
    }

    /**
     * Returns the singleton, auto-creating a fresh `GlobalEnvironment`
     * when none exists — without clearing the Phel registry, unlike
     * `initializeNew()`. This keeps compiled artifacts usable when
     * required outside a full compiler bootstrap: their emitted
     * `setNs(...)` / `hasDefinition(...)` calls simply operate on a
     * throwaway analyzer environment instead of throwing.
     */
    public static function getInstance(): GlobalEnvironmentInterface
    {
        $existing = GlobalEnvironmentRegistry::get();
        if ($existing instanceof GlobalEnvironmentInterface) {
            return $existing;
        }

        $env = new GlobalEnvironment();
        GlobalEnvironmentRegistry::set($env);

        return $env;
    }

    /**
     * @throws GlobalEnvironmentAlreadyInitializedException
     */
    public static function initialize(): GlobalEnvironmentInterface
    {
        if (GlobalEnvironmentRegistry::has()) {
            throw new GlobalEnvironmentAlreadyInitializedException();
        }

        $env = new GlobalEnvironment();
        GlobalEnvironmentRegistry::set($env);

        return $env;
    }

    /**
     * @internal
     */
    public static function initializeNew(): GlobalEnvironmentInterface
    {
        Phel::clear();
        RequireEvaluator::clearCache();
        $env = new GlobalEnvironment();
        GlobalEnvironmentRegistry::set($env);

        return $env;
    }

    /**
     * Replaces the singleton with a previously captured environment.
     * Used to restore state after a transient operation (e.g.
     * documentation/completion loading) that needed a clean environment.
     *
     * @internal
     */
    public static function setInstance(GlobalEnvironmentInterface $env): void
    {
        GlobalEnvironmentRegistry::set($env);
    }
}
