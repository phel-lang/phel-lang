<?php

declare(strict_types=1);

namespace PhelTest\Support;

use function escapeshellarg;
use function getenv;

/**
 * Lets a test's `bin/phel` subprocesses share one build cache per paratest
 * worker, so the bundled stdlib compiles once per worker instead of once per
 * subprocess. Every fixture project lives at a fresh temp path, so only the
 * stdlib (same path and source hash everywhere) is reused; the project's own
 * namespaces still compile cold.
 *
 * Opt-in on purpose: use it only for subprocesses that never run `phel build`,
 * never read the project's `.phel/cache` and never assert where the cache
 * lives. Outside paratest it does nothing.
 */
final class SharedStdlibCache
{
    /**
     * `PHEL_CACHE_DIR=... ` to prefix a shell command with, or '' outside paratest.
     */
    public static function envPrefix(): string
    {
        $dir = self::dir();

        return $dir === null ? '' : 'PHEL_CACHE_DIR=' . escapeshellarg($dir) . ' ';
    }

    public static function dir(): ?string
    {
        $dir = getenv('PHEL_TEST_SHARED_CACHE_DIR');

        return $dir === false || $dir === '' ? null : $dir;
    }
}
