<?php

declare(strict_types=1);

namespace Phel\Run\Application;

/**
 * Tracks which `.phel` files have already been evaluated in the current
 * session so eager startup loading and lazy on-demand bundle loading never
 * re-evaluate the same file. Shared between {@see NamespaceLoader} and
 * {@see LazyBundledNamespaceResolver}.
 *
 * Backed by process-wide static state because the loader is recreated per
 * `loadPhelNamespaces()` call while the set of already-evaluated files must
 * survive the whole session (matching the historical `static $loadedFiles`).
 */
final class NamespaceFileTracker
{
    /** @var array<string, true> */
    private static array $loadedFiles = [];

    public function isLoaded(string $file): bool
    {
        return isset(self::$loadedFiles[$file]);
    }

    public function markLoaded(string $file): void
    {
        self::$loadedFiles[$file] = true;
    }

    public static function reset(): void
    {
        self::$loadedFiles = [];
    }
}
