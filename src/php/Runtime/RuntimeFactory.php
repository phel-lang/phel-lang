<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\CompilerFactoryInterface;
use Phel\Runtime\Exceptions\ExceptionPrinterInterface;
use Phel\Runtime\Exceptions\HtmlExceptionPrinter;
use Phel\Runtime\Exceptions\RuntimeAlreadyInitializedException;
use Phel\Runtime\Exceptions\RuntimeNotInitializedException;
use Phel\Runtime\Exceptions\TextExceptionPrinter;

final class RuntimeFactory
{
    private static ?RuntimeInterface $instance = null;

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
            static::createExceptionPrinter(),
            static::createCompilerFactory(),
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
        self::$instance = new Runtime(
            $globalEnv,
            self::createExceptionPrinter(),
            self::createCompilerFactory(),
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

    private static function createCompilerFactory(): CompilerFactoryInterface
    {
        return new CompilerFactory();
    }
}
