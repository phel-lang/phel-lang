<?php

declare(strict_types=1);

namespace Phel\Compiler\Infrastructure;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\GlobalEnvironmentAlreadyInitializedException;
use Phel\Compiler\Domain\Analyzer\Exceptions\GlobalEnvironmentNotInitializedException;
use Phel\Lang\Registry;

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
