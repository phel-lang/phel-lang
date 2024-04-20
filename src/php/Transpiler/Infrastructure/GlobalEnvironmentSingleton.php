<?php

declare(strict_types=1);

namespace Phel\Transpiler\Infrastructure;

use Phel\Lang\Registry;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\GlobalEnvironmentAlreadyInitializedException;
use Phel\Transpiler\Domain\Analyzer\Exceptions\GlobalEnvironmentNotInitializedException;

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
     * @throws GlobalEnvironmentNotInitializedException
     */
    public static function getInstance(): GlobalEnvironmentInterface
    {
        if (!self::$instance instanceof GlobalEnvironmentInterface) {
            throw new GlobalEnvironmentNotInitializedException();
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
     * @interal
     */
    public static function initializeNew(): GlobalEnvironmentInterface
    {
        Registry::getInstance()->clear();
        self::$instance = new GlobalEnvironment();

        return self::$instance;
    }
}
