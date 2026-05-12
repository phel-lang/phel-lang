<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

/**
 * Process-wide slot for the single `GlobalEnvironmentInterface` shared by the
 * compiler pipeline. Owned by Domain so both Application and Infrastructure
 * collaborators can read/write the same state without forming a layer
 * dependency on each other.
 *
 * No business logic: side effects (Phel registry clearing, require-cache
 * eviction) live in the Application manager and Infrastructure singleton.
 *
 * @internal use `GlobalEnvironmentManagerInterface` from collaborators
 */
final class GlobalEnvironmentRegistry
{
    private static ?GlobalEnvironmentInterface $instance = null;

    public static function get(): ?GlobalEnvironmentInterface
    {
        return self::$instance;
    }

    public static function set(?GlobalEnvironmentInterface $env): void
    {
        self::$instance = $env;
    }

    public static function has(): bool
    {
        return self::$instance instanceof GlobalEnvironmentInterface;
    }
}
