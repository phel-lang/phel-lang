<?php

declare(strict_types=1);

namespace Phel\Compiler\Infrastructure;

use Phel;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\GlobalEnvironmentAlreadyInitializedException;
use Phel\Compiler\Domain\Evaluator\RequireEvaluator;

final class GlobalEnvironmentSingleton
{
    private static ?GlobalEnvironmentInterface $instance = null;

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function isInitialized(): bool
    {
        return self::$instance instanceof GlobalEnvironmentInterface;
    }

    /**
     * Ensures the GlobalEnvironment is initialized.
     * If already initialized, returns the existing instance.
     * If not, initializes a new one (which clears the Phel registry).
     */
    public static function ensureInitialized(): GlobalEnvironmentInterface
    {
        if (self::$instance instanceof GlobalEnvironmentInterface) {
            return self::$instance;
        }

        return self::initializeNew();
    }

    /**
     * Returns the singleton. Auto-creates a fresh `GlobalEnvironment`
     * when none exists — without clearing the Phel registry, unlike
     * `initializeNew()`. This keeps compiled artifacts usable when
     * required outside a full compiler bootstrap: their emitted
     * `setNs(...)` / `hasDefinition(...)` calls simply operate on a
     * throwaway analyzer environment instead of throwing.
     */
    public static function getInstance(): GlobalEnvironmentInterface
    {
        if (!self::$instance instanceof GlobalEnvironmentInterface) {
            self::$instance = new GlobalEnvironment();
        }

        return self::$instance;
    }

    /**
     * @throws GlobalEnvironmentAlreadyInitializedException
     */
    public static function initialize(): GlobalEnvironmentInterface
    {
        if (self::$instance instanceof GlobalEnvironmentInterface) {
            throw new GlobalEnvironmentAlreadyInitializedException();
        }

        self::$instance = new GlobalEnvironment();

        return self::$instance;
    }

    /**
     * @internal
     */
    public static function initializeNew(): GlobalEnvironmentInterface
    {
        Phel::clear();
        RequireEvaluator::clearCache();
        self::$instance = new GlobalEnvironment();

        return self::$instance;
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
        self::$instance = $env;
    }
}
