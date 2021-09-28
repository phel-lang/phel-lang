<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Environment;

use Phel\Compiler\Analyzer\Exceptions\GlobalEnvironmentAlreadyInitializedException;
use Phel\Compiler\Analyzer\Exceptions\GlobalEnvironmentNotInitializedException;

final class GlobalEnvironmentSingleton
{
    private static ?GlobalEnvironmentInterface $instance = null;

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function isInitialized(): bool
    {
        return self::$instance !== null;
    }

    /**
     * @throws GlobalEnvironmentNotInitializedException
     */
    public static function getInstance(): GlobalEnvironmentInterface
    {
        if (null === self::$instance) {
            throw new GlobalEnvironmentNotInitializedException();
        }

        return self::$instance;
    }

    /**
     * @throws GlobalEnvironmentAlreadyInitializedException
     */
    public static function initialize(): GlobalEnvironmentInterface
    {
        if (self::$instance !== null) {
            throw new GlobalEnvironmentAlreadyInitializedException();
        }

        self::$instance = new GlobalEnvironment();

        return self::$instance;
    }

    /**
     * @interal
     */
    public static function initializeNew(): GlobalEnvironmentInterface
    {
        unset($GLOBALS['__phel']);
        self::$instance = new GlobalEnvironment();

        return self::$instance;
    }
}
