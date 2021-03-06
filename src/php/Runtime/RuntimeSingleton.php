<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\CompilerFacade;
use Phel\Runtime\Exceptions\ExceptionPrinterInterface;
use Phel\Runtime\Exceptions\HtmlExceptionPrinter;
use Phel\Runtime\Exceptions\RuntimeAlreadyInitializedException;
use Phel\Runtime\Exceptions\RuntimeNotInitializedException;
use Phel\Runtime\Exceptions\TextExceptionPrinter;

final class RuntimeSingleton
{
    private static ?RuntimeInterface $instance = null;

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function isInitialized(): bool
    {
        return self::$instance !== null;
    }

    /**
     * @throws RuntimeNotInitializedException
     */
    public static function getInstance(): RuntimeInterface
    {
        if (null === self::$instance) {
            throw new RuntimeNotInitializedException();
        }

        return self::$instance;
    }

    /**
     * @throws RuntimeAlreadyInitializedException
     */
    public static function initialize(
        ?GlobalEnvironmentInterface $globalEnv = null,
        ?string $cacheDirectory = null
    ): RuntimeInterface {
        if (self::$instance !== null) {
            throw new RuntimeAlreadyInitializedException();
        }

        self::$instance = new Runtime(
            $globalEnv ?? new GlobalEnvironment(),
            self::createExceptionPrinter(),
            self::createCompilerFacade(),
            $cacheDirectory
        );

        return self::$instance;
    }

    /**
     * @interal
     */
    public static function initializeNew(
        GlobalEnvironmentInterface $globalEnv,
        string $cacheDirectory = null
    ): RuntimeInterface {
        unset($GLOBALS['__phel']);
        self::$instance = new Runtime(
            $globalEnv,
            self::createExceptionPrinter(),
            self::createCompilerFacade(),
            $cacheDirectory
        );

        return self::$instance;
    }

    private static function createExceptionPrinter(): ExceptionPrinterInterface
    {
        if (PHP_SAPI === 'cli') {
            return TextExceptionPrinter::create();
        }

        return HtmlExceptionPrinter::create();
    }

    /**
     * We cannot use the dependencyProvider here because the Singleton needs the static instance.
     */
    private static function createCompilerFacade(): CompilerFacade
    {
        return new CompilerFacade();
    }
}
