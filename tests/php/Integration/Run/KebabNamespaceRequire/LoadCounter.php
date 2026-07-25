<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\KebabNamespaceRequire;

/**
 * Counts how often a fixture namespace's top level ran. A namespace that is
 * already loaded must not be evaluated a second time, and a plain `def` cannot
 * observe that on its own because the re-run would just overwrite it.
 */
final class LoadCounter
{
    /** @var array<string, int> */
    private static array $counts = [];

    public static function record(string $namespace): void
    {
        self::$counts[$namespace] = (self::$counts[$namespace] ?? 0) + 1;
    }

    public static function countFor(string $namespace): int
    {
        return self::$counts[$namespace] ?? 0;
    }
}
