<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

/**
 * Provides fully qualified class names for runtime code generation.
 *
 * This allows Domain layer code to emit PHP code that references
 * Infrastructure classes without creating a direct import dependency.
 * The actual classes must exist at runtime but the Domain layer
 * doesn't need to import them during compilation.
 */
final class RuntimeClassReference
{
    /** Fully qualified class name for the GlobalEnvironmentSingleton. */
    public const string GLOBAL_ENVIRONMENT_SINGLETON = '\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton';

    /** Fully qualified class name for the Symbol class. */
    public const string SYMBOL = '\Phel\Lang\Symbol';

    /** Fully qualified class name for the BuildFacade. */
    public const string BUILD_FACADE = '\Phel\Build\BuildFacade';

    /** Fully qualified class name for the Phel class. */
    public const string PHEL = '\Phel';

    private function __construct()
    {
        // Prevent instantiation
    }
}
