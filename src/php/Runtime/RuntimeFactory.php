<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\CompilerFactoryInterface;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\GlobalEnvironmentInterface;
use Phel\Exceptions\ExceptionPrinterInterface;
use Phel\Exceptions\HtmlExceptionPrinter;
use Phel\Exceptions\TextExceptionPrinter;
use RuntimeException;

final class RuntimeFactory
{
    private static ?RuntimeInterface $instance = null;

    public static function initialize(
        ?GlobalEnvironmentInterface $globalEnv = null,
        ?string $cacheDirectory = null
    ): RuntimeInterface {
        if (self::$instance !== null) {
            throw new RuntimeException('Runtime is already initialized');
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
            return TextExceptionPrinter::readableWithStyle();
        }

        return HtmlExceptionPrinter::create();
    }

    private static function createCompilerFactory(): CompilerFactoryInterface
    {
        return new CompilerFactory();
    }

    public static function getInstance(): RuntimeInterface
    {
        if (null === self::$instance) {
            throw new RuntimeException('Runtime must first be initialized. Call Runtime::initialize()');
        }

        return self::$instance;
    }
}
