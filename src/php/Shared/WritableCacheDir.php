<?php

declare(strict_types=1);

namespace Phel\Shared;

use function is_dir;
use function is_writable;
use function mkdir;

/**
 * Answers whether a cache directory can actually be written to, creating it
 * when possible. Disk caches must degrade to their in-memory/null variants
 * instead of fataling in read-only environments (e.g. the NixOS build
 * sandbox, where the resolved project root is `/` and there is no writable
 * HOME) — Gacela's FileCache and the build caches all throw an uncaught
 * RuntimeException on a failed `mkdir`.
 */
final class WritableCacheDir
{
    /** @var array<string, bool> */
    private static array $usableByDir = [];

    public static function isUsable(string $dir): bool
    {
        return self::$usableByDir[$dir] ??= self::check($dir);
    }

    /**
     * Reset the memoized results (useful for testing).
     */
    public static function reset(): void
    {
        self::$usableByDir = [];
    }

    private static function check(string $dir): bool
    {
        if ($dir === '') {
            return false;
        }

        if (is_dir($dir)) {
            return is_writable($dir);
        }

        // Creating the dir here is not a side effect to avoid: it is the same
        // dir the gated cache would create lazily on its first write.
        return @mkdir($dir, 0o777, true) || is_dir($dir);
    }
}
